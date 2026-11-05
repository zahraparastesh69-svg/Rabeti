<?php
declare(strict_types=1);

/**
 * ShippingReturnsService
 *
 * مدیریت ارسال و مرجوعی:
 *  - shipOrder(int orderId, array {carrier, tracking_code, shipped_at})
 *  - deliverOrder(int orderId, datetime deliveredAt)
 *  - markReturnRequested(int refundRequestId, string reason)
 *  - markReturnReceived(int refundRequestId, int qty, bool sellable)
 *
 * این کلاس stateless است؛ فقط منطق و کوئری‌ها در آن نگه‌داری می‌شوند.
 */

final class ShippingReturnsService
{
    /**
     * ثبت خروج محموله از انبار و شروع ارسال.
     *
     * @param PDO   $db
     * @param int   $orderId
     * @param array{
     *   carrier?:string,
     *   tracking_code?:string,
     *   shipped_at?:string
     * } $data
     * @param int|null $adminId
     * @return bool
     * @throws RuntimeException
     */
    public static function shipOrder(
        PDO $db,
        int $orderId,
        array $data = [],
        ?int $adminId = null
    ): bool {
        $carrier      = trim((string)($data['carrier'] ?? ''));
        $trackingCode = trim((string)($data['tracking_code'] ?? ''));
        $shippedAt    = !empty($data['shipped_at']) ? $data['shipped_at'] : date('Y-m-d H:i:s');

        $db->beginTransaction();
        try {
            // قفل ردیف سفارش و shipment
            $stmt = $db->prepare("
                SELECT o.id, o.status, o.payment_status, o.fulfillment_status, o.shipping_status,
                       o.user_id,
                       s.id AS shipment_id
                FROM orders o
                LEFT JOIN order_shipments s ON s.order_id = o.id
                WHERE o.id = ?
                FOR UPDATE
            ");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();

            if (!$order) {
                $db->rollBack();
                throw new RuntimeException("سفارش یافت نشد");
            }

            // اعتبارسنجی: سفارش باید packed (آماده ارسال) باشد
            $allowed = ['packed'];
            if (!in_array($order['status'], $allowed, true)
                && $order['shipping_status'] !== 'ready'
                && $order['shipping_status'] !== 'packing') {
                $db->rollBack();
                throw new RuntimeException(
                    "سفارش در وضعیت «{$order['status']}» است؛ برای ارسال باید «packed» باشد."
                );
            }

            // به‌روزرسانی order_shipments
            if ($order['shipment_id']) {
                $db->prepare("
                    UPDATE order_shipments
                    SET tracking_code = ?, carrier = ?, shipped_at = ?
                    WHERE id = ?
                ")->execute([
                    $trackingCode !== '' ? $trackingCode : null,
                    $carrier !== '' ? $carrier : null,
                    $shippedAt,
                    (int)$order['shipment_id'],
                ]);
            }

            // به‌روزرسانی orders
            $db->prepare("
                UPDATE orders
                SET shipping_status = 'in_transit',
                    status = CASE WHEN status = 'packed' THEN 'shipped' ELSE status END,
                    shipping_method = COALESCE(
                        shipping_method,
                        (SELECT method FROM order_shipments WHERE order_id = ? LIMIT 1)
                    ),
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([$orderId, $orderId]);

            // آزادسازی رزرو و خروج فیزیکی کالا
            $items = $db->prepare("
                SELECT oi.id, oi.product_id, oi.qty_reserved
                FROM order_items oi
                WHERE oi.order_id = ? AND oi.qty_reserved > 0
                FOR UPDATE
            ");
            $items->execute([$orderId]);
            foreach ($items as $it) {
                // کاهش reserved و qty_physical
                $db->prepare("
                    UPDATE inventory
                    SET reserved = GREATEST(0, reserved - ?),
                        qty_physical = qty_physical - ?
                    WHERE product_id = ? AND qty_physical >= ?
                ")->execute([
                    (int)$it['qty_reserved'],
                    (int)$it['qty_reserved'],
                    (int)$it['product_id'],
                    (int)$it['qty_reserved'],
                ]);

                // به‌روزرسانی order_items: qty_delivered = qty_reserved
                $db->prepare("
                    UPDATE order_items
                    SET qty_delivered = qty_reserved
                    WHERE id = ?
                ")->execute([(int)$it['id']]);

                // ثبت در stock_movements
                $db->prepare("
                    INSERT INTO stock_movements
                    (product_id, movement_type, quantity, reference_type, reference_id,
                     order_id, order_item_id, reason, created_by, ip)
                    VALUES (?, 'ship', ?, 'order', ?, ?, ?, ?, ?, ?)
                ")->execute([
                    (int)$it['product_id'],
                    (int)$it['qty_reserved'],
                    $orderId,
                    $orderId,
                    (int)$it['id'],
                    'خروج برای ارسال سفارش',
                    $adminId,
                    $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                ]);
            }

            // audit log
            $db->prepare("
                INSERT INTO audit_logs
                (user_id, action, reason, context, entity, entity_id, ip)
                VALUES (?, 'order.shipped', ?, ?, 'order', ?, ?)
            ")->execute([
                $adminId,
                'ثبت ارسال سفارش',
                json_encode([
                    'tracking_code' => $trackingCode,
                    'carrier' => $carrier,
                    'shipped_at' => $shippedAt,
                ], JSON_UNESCAPED_UNICODE),
                $orderId,
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            ]);

            $db->commit();
            return true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /**
     * ثبت تحویل سفارش به مشتری.
     *
     * @param PDO $db
     * @param int $orderId
     * @param string|null $deliveredAt
     * @param int|null $adminId
     * @return bool
     */
    public static function deliverOrder(
        PDO $db,
        int $orderId,
        ?string $deliveredAt = null,
        ?int $adminId = null
    ): bool {
        $deliveredAt = $deliveredAt ?: date('Y-m-d H:i:s');

        $db->beginTransaction();
        try {
            $db->prepare("
                UPDATE orders
                SET shipping_status = 'delivered',
                    status = 'delivered',
                    fulfillment_status = 'completed',
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([$orderId]);

            $db->prepare("
                UPDATE order_shipments
                SET delivered_at = ?
                WHERE order_id = ?
            ")->execute([$deliveredAt, $orderId]);

            $db->prepare("
                INSERT INTO audit_logs
                (user_id, action, reason, context, entity, entity_id, ip)
                VALUES (?, 'order.delivered', ?, ?, 'order', ?, ?)
            ")->execute([
                $adminId,
                'تحویل به مشتری',
                json_encode(['delivered_at' => $deliveredAt], JSON_UNESCAPED_UNICODE),
                $orderId,
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            ]);

            $db->commit();
            return true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /**
     * ثبت شکست ارسال (مثلاً گم شدن یا برگشت با مشتری).
     *
     * @param PDO $db
     * @param int $orderId
     * @param string $reason
     * @param int|null $adminId
     * @return bool
     */
    public static function failShipment(
        PDO $db,
        int $orderId,
        string $reason,
        ?int $adminId = null
    ): bool {
        $db->beginTransaction();
        try {
            $db->prepare("
                UPDATE orders
                SET shipping_status = 'failed',
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([$orderId]);

            $db->prepare("
                UPDATE order_shipments
                SET return_reason = ?
                WHERE order_id = ?
            ")->execute([$reason, $orderId]);

            $db->prepare("
                INSERT INTO audit_logs
                (user_id, action, reason, context, entity, entity_id, ip)
                VALUES (?, 'order.shipping_failed', ?, ?, 'order', ?, ?)
            ")->execute([
                $adminId,
                'شکست در ارسال',
                json_encode(['reason' => $reason], JSON_UNESCAPED_UNICODE),
                $orderId,
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            ]);

            $db->commit();
            return true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /**
     * ثبت درخواست بازگشت کالا (مرجوعی).
     *
     * @param PDO $db
     * @param int $refundRequestId
     * @param string $reason
     * @param int|null $adminId
     * @return bool
     */
    public static function markReturnRequested(
        PDO $db,
        int $refundRequestId,
        string $reason,
        ?int $adminId = null
    ): bool {
        $db->prepare("
            UPDATE refund_requests
            SET return_status = 'requested',
                return_reason = ?
            WHERE id = ?
        ")->execute([$reason, $refundRequestId]);

        $db->prepare("
            INSERT INTO audit_logs
            (user_id, action, reason, context, entity, entity_id, ip)
            VALUES (?, 'refund.return_requested', ?, ?, 'refund', ?, ?)
        ")->execute([
            $adminId,
            'درخواست بازگشت کالا',
            json_encode(['reason' => $reason], JSON_UNESCAPED_UNICODE),
            $refundRequestId,
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        ]);

        return true;
    }

    /**
     * ثبت دریافت کالای برگشتی در انبار.
     *
     * اگر $sellable=true: به qty_physical اضافه می‌شود (قابل فروش مجدد)
     * اگر $sellable=false: به unsellable اضافه می‌شود (نقص/خسارت)
     *
     * @param PDO $db
     * @param int $refundRequestId
     * @param int $qty
     * @param bool $sellable
     * @param int|null $adminId
     * @return bool
     */
    public static function markReturnReceived(
        PDO $db,
        int $refundRequestId,
        int $qty,
        bool $sellable,
        ?int $adminId = null
    ): bool {
        if ($qty < 0) {
            throw new InvalidArgumentException("مقدار بازگشتی نمی‌تواند منفی باشد");
        }

        $db->beginTransaction();
        try {
            // دریافت اطلاعات درخواست
            $stmt = $db->prepare("
                SELECT id, invoice_id, order_item_id, return_status
                FROM refund_requests
                WHERE id = ?
                FOR UPDATE
            ");
            $stmt->execute([$refundRequestId]);
            $refund = $stmt->fetch();

            if (!$refund) {
                $db->rollBack();
                throw new RuntimeException("درخواست بازگشت یافت نشد");
            }

            // به‌روزرسانی refund_requests
            $db->prepare("
                UPDATE refund_requests
                SET return_status = ?,
                    return_received_at = NOW(),
                    return_qty = ?
                WHERE id = ?
            ")->execute([
                $sellable ? 'received_sellable' : 'received_unsellable',
                $qty,
                $refundRequestId,
            ]);

            // اگر order_item_id وجود دارد، موجودی آن را به‌روزرسانی کن
            if (!empty($refund['order_item_id'])) {
                $stmt = $db->prepare("SELECT product_id FROM order_items WHERE id = ? LIMIT 1");
                $stmt->execute([(int)$refund['order_item_id']]);
                $productId = (int)$stmt->fetchColumn();

                if ($productId > 0) {
                    // قفل inventory
                    $db->prepare("SELECT qty_physical, unsellable FROM inventory WHERE product_id = ? FOR UPDATE")
                        ->execute([$productId]);
                    $row = $db->query("SELECT qty_physical, unsellable FROM inventory WHERE product_id = $productId")->fetch();

                    if ($row) {
                        if ($sellable) {
                            $db->prepare("UPDATE inventory SET qty_physical = qty_physical + ? WHERE product_id = ?")
                                ->execute([$qty, $productId]);
                        } else {
                            $db->prepare("UPDATE inventory SET unsellable = unsellable + ? WHERE product_id = ?")
                                ->execute([$qty, $productId]);
                        }

                        // ثبت در stock_movements
                        $db->prepare("
                            INSERT INTO stock_movements
                            (product_id, movement_type, quantity, reference_type, reference_id,
                             order_id, order_item_id, reason, created_by, ip)
                            VALUES (?, ?, ?, 'refund', ?, NULL, ?, ?, ?, ?)
                        ")->execute([
                            $productId,
                            $sellable ? 'return_sellable' : 'return_unsellable',
                            $qty,
                            $refundRequestId,
                            (int)$refund['order_item_id'],
                            $sellable
                                ? 'دریافت مرجوعی قابل فروش'
                                : 'دریافت مرجوعی غیرقابل فروش (خسارت)',
                            $adminId,
                            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                        ]);
                    }
                }
            }

            // audit
            $db->prepare("
                INSERT INTO audit_logs
                (user_id, action, reason, context, entity, entity_id, ip)
                VALUES (?, 'refund.return_received', ?, ?, 'refund', ?, ?)
            ")->execute([
                $adminId,
                $sellable ? 'مرجوعی قابل فروش دریافت شد' : 'مرجوعی غیرقابل فروش دریافت شد',
                json_encode([
                    'qty' => $qty,
                    'sellable' => $sellable,
                ], JSON_UNESCAPED_UNICODE),
                $refundRequestId,
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            ]);

            $db->commit();
            return true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /**
     * رد درخواست بازگشت (کالا مشکل از سمت مشتری).
     *
     * @param PDO $db
     * @param int $refundRequestId
     * @param string $reason
     * @param int|null $adminId
     * @return bool
     */
    public static function rejectReturn(
        PDO $db,
        int $refundRequestId,
        string $reason,
        ?int $adminId = null
    ): bool {
        $db->prepare("
            UPDATE refund_requests
            SET return_status = 'rejected',
                return_reason = ?
            WHERE id = ?
        ")->execute([$reason, $refundRequestId]);

        $db->prepare("
            INSERT INTO audit_logs
            (user_id, action, reason, context, entity, entity_id, ip)
            VALUES (?, 'refund.return_rejected', ?, ?, 'refund', ?, ?)
        ")->execute([
            $adminId,
            'رد درخواست بازگشت',
            json_encode(['reason' => $reason], JSON_UNESCAPED_UNICODE),
            $refundRequestId,
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        ]);

        return true;
    }
}
