<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Table;
use PhpOffice\PhpSpreadsheet\Worksheet\Table\TableStyle;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConvertController extends Controller
{
    public function export($bg = '', $dept_cd = '')
    {
        $results = DB::connection('BFIE')->select("
            select * from mgr.v_gl_budget_export
            where ver_cd = ? and dept_cd = ?
            order by entity_cd, dept_cd
        ", [$bg, $dept_cd]);

        $entityCd = $results[0]->entity_cd ?? 'UNKNOWN';

        $dept_descs = $results[0]->dept_descs ?? 'UNKNOWN';

        $initial_company = $results[0]->initial_company ?? 'COMBINE';

        $grouped = collect($results)->groupBy('entity_cd');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet(); // disini
        // $sheet->mergeCells('A1:I1');
        // $sheet->setCellValue('A1', 'Report Budget Version Listing');
        // $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        // $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        // $row = 3;
        $row = 1;

        foreach ($grouped as $entityCd => $items) {
           $startRow = $row;

            // Header
            $sheet->fromArray([
                'Version Code', 'Acct Code', 'Acct Name', 'Div Name', 'Dept Name',
                'Budget Amount', 'Commit Amount', 'Actual Amount', 'Balance Amount'
            ], null, "A{$row}");
            $sheet->getStyle("A{$row}:I{$row}")->getFont()->setBold(true);
            $row++;

            // Group by dept_cd per entity
            $deptGroups = collect($items)->groupBy('dept_cd');

            foreach ($deptGroups as $dept => $records) {
                $totals = ['base_budget' => 0, 'commit_amt' => 0, 'todate_amt' => 0, 'balance' => 0];

                foreach ($records as $item) {
                    $sheet->fromArray([
                        $item->ver_cd,
                        $item->acct_cd,
                        $item->acct_descs,
                        $item->div_descs,
                        $item->dept_descs,
                        $item->base_budget,
                        $item->commit_amt,
                        $item->todate_amt,
                        $item->balance,
                    ], null, "A{$row}");

                    $sheet->getStyle("F{$row}:I{$row}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0.00');

                    $totals['base_budget'] += $item->base_budget;
                    $totals['commit_amt'] += $item->commit_amt;
                    $totals['todate_amt'] += $item->todate_amt;
                    $totals['balance'] += $item->balance;

                    $row++;
                }

                //Border & stripe Style
                $endRow = $row - 1; // gunakan -1 agar baris akhir mencakup semua data
                $tableRange = "A{$startRow}:I{$endRow}";

                // Tambahkan border
                $sheet->getStyle($tableRange)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => ['argb' => 'FF000000'],
                        ],
                    ],
                ]);

                // Tambahkan tabel (opsional, ini hanya kosmetik di Excel)
                $table = new \PhpOffice\PhpSpreadsheet\Worksheet\Table($tableRange);
                $tableStyle = new \PhpOffice\PhpSpreadsheet\Worksheet\Table\TableStyle();
                $tableStyle->setShowRowStripes(true);
                $table->setStyle($tableStyle);
                $sheet->addTable($table);

                // TOTAL row
                // $sheet->setCellValue("E{$row}", 'TOTAL');
                // $sheet->setCellValue("F{$row}", $totals['base_budget'] ?? 0);
                // $sheet->setCellValue("G{$row}", $totals['commit_amt'] ?? 0);
                // $sheet->setCellValue("H{$row}", $totals['todate_amt'] ?? 0);
                // $sheet->setCellValue("I{$row}", $totals['balance'] ?? 0);

                // // Tambahkan ini setelah fromArray
                // $sheet->getStyle("A{$row}:I{$row}")->getFont()->setBold(true);

                // // Format kolom I sampai L sebagai currency
                // $sheet->getStyle("F{$row}:I{$row}")
                //     ->getNumberFormat()
                //     ->setFormatCode('#,##0.00');

                // $row++;

                // // Tambahkan baris kosong sebagai pemisah
                // $row++;
            }
        }

        // Auto size
        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Generate file name
        // $date = now()->format('dmY');
        $filename = "{$initial_company}_{$dept_cd}.xlsx";
        // $filename = "BTID_{$entityCd}_{$bg}-{$dept_cd}_{$date}.xlsx";
        $filepath = storage_path("app/{$filename}");

        $writer = new Xlsx($spreadsheet);
        $writer->save($filepath);

        // Ambil data FTP dari database
        $ftpConfig = DB::connection('BFIE')->table('mgr.ftp_spec')->first();

        if (!$ftpConfig) {
            return response()->json(['error' => 'FTP configuration not found.'], 500);
        }

        // Koneksi ke FTP
        $ftpConn = ftp_connect($ftpConfig->FTPServer, $ftpConfig->FTPPort, 30);
        if (!$ftpConn) {
            return response()->json(['error' => 'Could not connect to FTP server.'], 500);
        }

        $login = ftp_login($ftpConn, $ftpConfig->FTPUser, $ftpConfig->FTPPassword);
        if (!$login) {
            ftp_close($ftpConn);
            return response()->json(['error' => 'FTP login failed.'], 500);
        }

        ftp_pasv($ftpConn, true);

        // === Step: Navigasi dan buat folder jika belum ada ===
        // Masuk ke folder ifca-att (karena sudah pasti ada)
        if (!ftp_chdir($ftpConn, 'ifca-att')) {
            ftp_close($ftpConn);
            return response()->json(['error' => 'Could not change to ifca-att folder.'], 500);
        }

        // Daftar folder yang ingin dipastikan ada di dalam ifca-att
        $subFolders = ['Budget_Listing'];

        foreach ($subFolders as $folder) {
            if (!@ftp_chdir($ftpConn, $folder)) {
                // Folder belum ada, buat
                if (ftp_mkdir($ftpConn, $folder)) {
                    // Coba set full permission jika didukung
                    @ftp_site($ftpConn, "CHMOD 0777 $folder");
                    // Masuk ke folder setelah buat
                    ftp_chdir($ftpConn, $folder);
                } else {
                    ftp_close($ftpConn);
                    return response()->json(['error' => "Failed to create folder $folder"], 500);
                }
            }
        }

        // Kembali ke root sebelum upload
        // ftp_chdir($ftpConn, '/');

        // === Step: Upload file ===
        $remotePath = $filename; // hanya nama file, karena kita sudah di folder target
        $upload = ftp_put($ftpConn, $remotePath, $filepath, FTP_BINARY);

        if (!$upload) {
            return response()->json(['error' => 'Failed to upload file to FTP.'], 500);
        }

        // Optional: hapus file lokal setelah upload
        unlink($filepath);

        $fileUrl = rtrim($ftpConfig->URLPDF, '/') . '/ifca-att/Budget_Listing/' . $filename;

        $existing = DB::connection('BFIE')->table('mgr.export_log')
            ->where('ver_cd', $bg)
            ->where('file_name', $filename)
            ->where('dept_cd', $dept_cd)
            ->where('entity_cd', $entityCd)
            ->first();

        $data = [
            'file_url' => $fileUrl,
            'audit_date' => DB::raw('GETDATE()'),
        ];

        if ($existing) {
            // Update
            DB::connection('BFIE')->table('mgr.export_log')
                ->where('ver_cd', $bg)
                ->where('file_name', $filename)
                ->where('dept_cd', $dept_cd)
                ->where('entity_cd', $entityCd)
                ->update($data);
        } else {
            // Insert
            DB::connection('BFIE')->table('mgr.export_log')
                ->insert(array_merge(['ver_cd' => $bg, 'file_name' => $filename, 'dept_cd' => $dept_cd, 'entity_cd' => $entityCd], $data));
        }

        return response()->json(['success' => true, 'message' => "File uploaded to FTP as {$remotePath}"]);
    }
}
