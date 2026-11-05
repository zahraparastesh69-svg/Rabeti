<?php
/**
 * CSV exporter helper.
 *
 * استفاده:
 *   $exporter = new CsvExporter('inventory-' . date('Y-m-d'));
 *   $exporter->header(['کد', 'نام', 'موجودی', 'فروش']);
 *   foreach ($rows as $r) { $exporter->row([$r['code'], $r['name'], $r['qty'], $r['sold']]); }
 *   $exporter->end();
 */

final class CsvExporter
{
    private $out;
    private bool $started = false;

    public function __construct(string $filename)
    {
        $this->filename = $filename;
    }

    public function header(array $columns): void
    {
        if ($this->started) return;
        $this->started = true;

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $this->filename . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $this->out = fopen('php://output', 'w');
        // BOM برای Excel UTF-8
        fprintf($this->out, "\xEF\xBB\xBF");
        fputcsv($this->out, $columns);
    }

    public function row(array $values): void
    {
        if (!$this->started) return;
        fputcsv($this->out, $values);
    }

    public function end(): void
    {
        if (!$this->started) return;
        fclose($this->out);
        $this->started = false;
        exit;
    }
}
