<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Table;
use PhpOffice\PhpSpreadsheet\Worksheet\Table\TableStyle;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Mail\AssetBlastMail;

class BlastFaController extends Controller
{
    public function blast($dept_cd = '')
    {
        $results = DB::connection('BTID')->select("
            SELECT *
            FROM mgr.v_fa_alert_asset_dept
            WHERE dept_cd = ? AND status != 'Audit'
            ORDER BY entity_cd, dept_cd
        ", [$dept_cd]);

        if (empty($results)) {
            \Log::warning("Tidak ada data ditemukan untuk dept_cd: {$dept_cd}");
            return response()->json(['message' => 'Tidak ada data ditemukan'], 404);
        }

        $entityCd = $results[0]->entity_cd ?? 'UNKNOWN';

        $dept_descs = $results[0]->dept_descs ?? 'UNKNOWN';

        $staff_name = $results[0]->staff_name ?? 'TEAM';

        $grouped = collect($results)->groupBy('entity_cd');

        $emailList = array_map('trim', explode(',', $results[0]->email_to ?? ''));
        $emailCC = array_map('trim', explode(',', $results[0]->email_cc ?? ''));

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet(); // disini

        // $row = 3;
        $row = 1;

        foreach ($grouped as $entityCd => $items) {
           $startRow = $row;

            // Header
            $sheet->fromArray([
                'Entity Name', 'Reg ID', 'Fixed Asset Name', 'Acquisition Date', 'Cost Value'
            ], null, "A{$row}");
            $sheet->getStyle("A{$row}:E{$row}")->getFont()->setBold(true);
            $row++;

            // Group by dept_cd per entity
            $deptGroups = collect($items)->groupBy('dept_cd');

            foreach ($deptGroups as $dept => $records) {
                $totals = ['cost_value' => 0];

                foreach ($records as $item) {
                    $sheet->fromArray([
                        $item->entity_name,
                        $item->reg_id,
                        $item->asset_descs,
                        $item->acquire_date,
                        $item->cost_value,
                    ], null, "A{$row}");

                    $sheet->getStyle("E{$row}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0.00');

                    // $totals['cost_value'] += $item->cost_value;
                    $cleanCost = str_replace(',', '', $item->cost_value);
                    $totals['cost_value'] += (float) $cleanCost;

                    $row++;
                }

                //Border & stripe Style
                $endRow = $row - 1; // gunakan -1 agar baris akhir mencakup semua data
                $tableRange = "A{$startRow}:E{$endRow}";

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
            }
        }

        // Auto size
        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $cleanDeptDescs = preg_replace('/[^A-Za-z0-9_]/', '_', str_replace(' ', '_', $dept_descs));

        // Save file
        $filename = "fa_{$cleanDeptDescs}.xlsx";
        $filepath = storage_path("app/{$filename}");
        $writer = new Xlsx($spreadsheet);
        $writer->save($filepath);

        // $to = 'user@example.com'; // Bisa juga ambil dari $item jika tersedia email per departemen
        // Mail::to($emailList)->send((new AssetBlastMail($dept_cd, $dept_descs))->attach($filepath));
        //Mail::to($emailList)->send(new AssetBlastMail($cleanDeptDescs, $staff_name, $filepath));
	try {
  		Mail::to($emailList)->cc($emailCC)->send(new AssetBlastMail($cleanDeptDescs, $staff_name, $filepath));
	} catch (\Exception $e) {
    		\Log::error("Email failed to send: " . $e->getMessage());
	}

        // Hapus file setelah kirim (opsional)
        unlink($filepath);
    }
}
