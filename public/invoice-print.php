<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/auth.php';

if (!is_logged_in()) exit('دسترسی غیرمجاز');

$userId = (int)($_SESSION['admin_id'] ?? $_SESSION['customer_id'] ?? 0);
$stmt = db()->prepare("
    SELECT i.*, o.*, u.full_name
    FROM invoices i
    JOIN orders o ON o.id = i.order_id
    JOIN users u ON u.id = i.user_id
    WHERE i.invoice_number = ? AND (i.user_id = ? OR ?)
    LIMIT 1
");
$isAdmin = is_admin_logged_in();
$stmt->execute([trim($_GET['inv'] ?? ''), $userId, $isAdmin ? 1 : 0]);
$inv = $stmt->fetch();
if (!$inv) exit('فاکتور یافت نشد');

$stmt = db()->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmt->execute([$inv['order_id']]);
$items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title>فاکتور <?= htmlspecialchars($inv['invoice_number']) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Vazir, Tahoma, sans-serif;
        }

        body {
            background: #f5f5f5;
            padding: 24px;
            color: #1a2b32;
        }

        .inv {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            border-radius: 12px;
            padding: 32px;
        }

        .inv-head {
            display: flex;
            justify-content: space-between;
            border-bottom: 3px solid #2A5F78;
            padding-bottom: 16px;
            margin-bottom: 20px;
        }

        .inv-head h1 {
            font-size: 1.3rem;
            color: #2A5F78;
        }

        .inv-head .meta {
            text-align: left;
            font-size: .85rem;
            line-height: 2;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 18px 0;
        }

        th {
            background: #2A5F78;
            color: #fff;
            padding: 10px;
            font-size: .82rem;
        }

        td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            font-size: .85rem;
            text-align: center;
        }

        .t-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: .9rem;
        }

        .t-row.grand {
            border-top: 2px solid #2A5F78;
            margin-top: 8px;
            padding-top: 12px;
            font-weight: 900;
            font-size: 1.05rem;
            color: #2A5F78;
        }

        .stamp {
            margin-top: 28px;
            display: flex;
            justify-content: space-between;
            font-size: .8rem;
            color: #666;
        }

        .addr {
            font-size: .8rem;
            line-height: 2;
            color: #555;
            margin-top: 10px;
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }

            .noprint {
                display: none !important;
            }

            .inv {
                border-radius: 0;
                padding: 10mm;
            }
        }
    </style>
</head>

<body>
    <div class="inv">
        <div class="inv-head">
            <div>
                <h1>🧦 تولیدی جوراب رابطی</h1>
                <small>فاکتور فروش</small>
            </div>
            <div class="meta">
                <b dir="ltr"><?= htmlspecialchars($inv['invoice_number']) ?></b><br>
                سفارش: <span dir="ltr"><?= htmlspecialchars($inv['order_number']) ?></span><br>
                تاریخ: <?= $inv['issued_at'] ?><br>
                وضعیت: <?= ['unpaid' => 'پرداخت‌نشده', 'partial' => 'پرداخت جزئی', 'paid' => 'پرداخت‌شده', 'void' => 'باطل'][$inv['status']] ?>
            </div>
        </div>

        <div class="t-row"><span>خریدار:</span><b><?= htmlspecialchars($inv['full_name']) ?></b></div>
        <div class="t-row"><span>موبایل:</span><span dir="ltr"><?= htmlspecialchars($inv['shipping_mobile']) ?></span></div>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>کد</th>
                    <th>محصول</th>
                    <th>تعداد</th>
                    <th>قیمت واحد</th>
                    <th>جمع</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $n => $it): ?>
                    <tr>
                        <td><?= $n + 1 ?></td>
                        <td dir="ltr"><?= htmlspecialchars($it['product_code']) ?></td>
                        <td><?= htmlspecialchars($it['product_name']) ?></td>
                        <td><?= number_format((int)$it['qty']) ?></td>
                        <td><?= number_format((int)$it['unit_price']) ?></td>
                        <td><b><?= number_format((int)$it['line_total']) ?></b></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="t-row"><span>جمع کل (<?= number_format(array_sum(array_column($items, 'qty'))) ?> جفت)</span><span><?= number_format((int)$inv['total']) ?> تومان</span></div>
        <div class="t-row"><span>پرداخت تأییدشده</span><span><?= number_format((int)$inv['paid_total']) ?> تومان</span></div>
        <div class="t-row grand"><span>مانده</span><span><?= number_format(max(0, (int)$inv['total'] - (int)$inv['paid_total'])) ?> تومان</span></div>

        <div class="addr">
            آدرس: تهران، شهرک صنعتی — خیابان تولید، پلاک ۱۲ | تلفن: ۰۲۱-۱۲۳۴۵۶۷۸
        </div>

        <div class="stamp">
            <span>امضای فروشنده: ................</span>
            <span>مهر و امضای خریدار: ................</span>
        </div>
    </div>
    <button class="noprint" onclick="window.print()"
        style="display:block;margin:16px auto;padding:12px 32px;background:#2A5F78;color:#fff;border:none;border-radius:10px;cursor:pointer;font-family:inherit;font-size:.9rem;">
        🖨️ چاپ فاکتور
    </button>
</body>

</html>