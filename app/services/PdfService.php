<?php

declare(strict_types=1);

final class PdfService
{
    private static bool $loaded = false;

    private static function loadTcpdf(): void
    {
        if (self::$loaded) {
            return;
        }

        $autoload = ROOT_PATH . '/vendor/autoload.php';

        if (!is_file($autoload)) {
            throw new RuntimeException(
                'Composer autoload پیدا نشد.'
            );
        }

        require_once $autoload;

        if (!class_exists('TCPDF')) {
            throw new RuntimeException(
                'TCPDF در Composer نصب نشده است.'
            );
        }

        self::$loaded = true;
    }

    public static function output(
        array $invoice
    ): void {
        self::loadTcpdf();

        $pdf = new TCPDF(
            'P',
            'mm',
            'A4',
            true,
            'UTF-8',
            false
        );

        $pdf->SetCreator('sock-b2b');
        $pdf->SetAuthor('sock-b2b');
        $pdf->SetTitle(
            'فاکتور ' .
                (string)$invoice['invoice_number']
        );

        $pdf->setRTL(true);

        $pdf->SetMargins(
            10,
            10,
            10
        );

        $pdf->SetAutoPageBreak(
            true,
            10
        );

        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        $pdf->SetFont(
            'dejavusans',
            '',
            9
        );

        $pdf->AddPage();

        $html = self::buildHtml(
            $invoice
        );

        $pdf->writeHTML(
            $html,
            true,
            false,
            true,
            false,
            ''
        );

        $filename =
            'invoice-' .
            preg_replace(
                '/[^A-Za-z0-9\-_]/',
                '',
                (string)$invoice['invoice_number']
            ) .
            '.pdf';

        $pdf->Output(
            $filename,
            'I'
        );
    }

    private static function buildHtml(
        array $invoice
    ): string {
        $e = static function ($value): string {
            return htmlspecialchars(
                (string)$value,
                ENT_QUOTES,
                'UTF-8'
            );
        };

        $money = static function ($value): string {
            return number_format(
                (float)$value,
                0,
                '.',
                ','
            ) . ' تومان';
        };

        $status =
            InvoiceService::statusLabel(
                (string)$invoice['invoice_status']
            );

        $itemsHtml = '';

        foreach (
            ($invoice['items'] ?? [])
            as $index => $item
        ) {
            $details = array_filter([
                $item['variant_name'] ?? '',
                $item['color_name'] ?? '',
                $item['size_label'] ?? '',
            ]);

            $itemsHtml .= '
                <tr>
                    <td align="center">'
                . ($index + 1) .
                '</td>

                    <td>
                        <b>'
                . $e(
                    $item['product_name']
                        ?? 'محصول'
                ) .
                '</b>
                        <br>
                        <small>'
                . $e(
                    implode(
                        ' · ',
                        $details
                    ) ?: '—'
                ) .
                '</small>
                    </td>

                    <td align="center">'
                . (int)(
                    $item['qty'] ?? 0
                ) .
                '</td>

                    <td align="right">'
                . $e(
                    $money(
                        $item['unit_price']
                            ?? 0
                    )
                ) .
                '</td>

                    <td align="right">'
                . $e(
                    $money(
                        $item['subtotal']
                            ?? 0
                    )
                ) .
                '</td>
                </tr>
            ';
        }

        $totals = $invoice['totals'] ?? [];

        return '
        <style>

            h1 {
                font-size: 18px;
            }

            .muted {
                color: #666;
            }

            .box {
                border: 1px solid #ddd;
                padding: 7px;
            }

            table {
                border-collapse: collapse;
            }

            th {
                background-color: #f2f2f2;
                font-weight: bold;
            }

            th,
            td {
                border: 1px solid #ddd;
                padding: 6px;
            }

            .total {
                font-size: 13px;
                font-weight: bold;
            }

        </style>

        <h1>
            فاکتور فروش
        </h1>

        <table width="100%" cellpadding="4">

            <tr>

                <td width="50%">
                    <b>شماره فاکتور:</b>
                    '
            . $e(
                $invoice['invoice_number']
            ) .
            '
                </td>

                <td width="50%">
                    <b>وضعیت:</b>
                    '
            . $e($status) .
            '
                </td>

            </tr>

            <tr>

                <td>
                    <b>مشتری:</b>
                    '
            . $e(
                $invoice['customer_name']
                    ?? '—'
            ) .
            '
                </td>

                <td>
                    <b>شماره سفارش:</b>
                    '
            . $e(
                $invoice['order_number']
                    ?? '—'
            ) .
            '
                </td>

            </tr>

        </table>

        <br>

        <table width="100%">

            <thead>

                <tr>

                    <th width="7%">
                        #
                    </th>

                    <th width="38%">
                        کالا
                    </th>

                    <th width="12%">
                        تعداد
                    </th>

                    <th width="21%">
                        قیمت واحد
                    </th>

                    <th width="22%">
                        مبلغ
                    </th>

                </tr>

            </thead>

            <tbody>
                '
            . $itemsHtml .
            '
            </tbody>

        </table>

        <br>

        <table width="100%">

            <tr>

                <td width="60%"></td>

                <td width="20%">
                    جمع کالاها
                </td>

                <td width="20%" align="right">
                    '
            . $e(
                $money(
                    $totals['subtotal']
                        ?? 0
                )
            ) .
            '
                </td>

            </tr>

            <tr>

                <td></td>

                <td>
                    تخفیف
                </td>

                <td align="right">
                    '
            . $e(
                $money(
                    $totals['discount']
                        ?? 0
                )
            ) .
            '
                </td>

            </tr>

            <tr>

                <td></td>

                <td>
                    مالیات
                </td>

                <td align="right">
                    '
            . $e(
                $money(
                    $totals['tax']
                        ?? 0
                )
            ) .
            '
                </td>

            </tr>

            <tr>

                <td></td>

                <td class="total">
                    مبلغ نهایی
                </td>

                <td
                    align="right"
                    class="total"
                >
                    '
            . $e(
                $money(
                    $totals['total']
                        ?? 0
                )
            ) .
            '
                </td>

            </tr>

        </table>
        ';
    }
}
