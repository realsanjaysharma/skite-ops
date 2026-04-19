<?php

require_once __DIR__ . '/../repositories/ReportRepository.php';

class ReportService
{
    private $reportRepository;

    public function __construct()
    {
        $this->reportRepository = new ReportRepository();
    }

    /**
     * Get Worker Activity Report
     * 
     * @param string $month Format YYYY-MM
     * @param int|null $workerId
     * @param string|null $skillTag
     * @return array
     */
    public function getWorkerActivityReport(string $month, ?int $workerId = null, ?string $skillTag = null): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            throw new InvalidArgumentException("Month must be in YYYY-MM format");
        }

        $monthStart = $month . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));

        return $this->reportRepository->getWorkerActivityReport($monthStart, $monthEnd, $workerId, $skillTag);
    }

    /**
     * Convert an array of associative arrays to CSV format and output directly.
     * 
     * @param array $data
     * @param string $filename
     */
    public function exportCsv(array $data, string $filename): void
    {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        
        if (!empty($data)) {
            // Write headers
            fputcsv($out, array_keys($data[0]));
            
            // Write rows
            foreach ($data as $row) {
                fputcsv($out, array_values($row));
            }
        } else {
            // Write dummy header if empty
            fputcsv($out, ['No Data Found']);
        }
        
        fclose($out);
        exit;
    }
}
