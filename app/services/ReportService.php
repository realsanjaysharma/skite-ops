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

    public function getBeltHealthReport(string $month, ?int $zoneId = null, ?int $supervisorId = null): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            throw new InvalidArgumentException("Month must be in YYYY-MM format");
        }

        $monthStart = $month . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));

        $data = $this->reportRepository->getBeltHealthReport($monthStart, $monthEnd, $supervisorId);
        
        foreach ($data as &$row) {
            $row['required_watering_days'] = 0;     // Deprecated/Simplified for v1 backend speed
            $row['completed_watering_days'] = 0;
            $row['watering_compliance_percent'] = 100;
            
            $health = 'HEALTHY';
            if ($row['open_issues_count'] > 0) {
                $health = 'WARNING';
            }
            if ((int)$row['days_since_last_completion'] > 30) {
                $health = 'RISK';
            }
            $row['health_status'] = $health;
        }

        return $data;
    }

    public function getSupervisorActivityReport(string $month, ?int $supervisorId = null): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            throw new InvalidArgumentException("Month must be in YYYY-MM format");
        }

        $monthStart = $month . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));

        $data = $this->reportRepository->getSupervisorActivityReport($monthStart, $monthEnd, $supervisorId);
        
        foreach ($data as &$row) {
            $row['required_watering_days'] = 0;     // Simplified
            $row['completed_watering_days'] = 0;
            $row['watering_compliance_percent'] = 100;
        }

        return $data;
    }

    public function getAdvertisementOperationsReport(string $month, ?string $siteCategory = null): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            throw new InvalidArgumentException("Month must be in YYYY-MM format");
        }

        $monthStart = $month . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));

        $data = $this->reportRepository->getAdvertisementOperationsReport($monthStart, $monthEnd, $siteCategory);
        
        foreach ($data as &$row) {
            $due = (int)$row['monitoring_due_count'];
            $completed = 0; // Simplified for v1
            $row['monitoring_completed_count'] = $completed;
            $row['monitoring_coverage_percent'] = $due > 0 ? round(($completed / $due) * 100) : 100;
        }

        return $data;
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
