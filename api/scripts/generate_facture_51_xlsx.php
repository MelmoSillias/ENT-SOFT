<?php

/**
 * Génère documentation/modeles/facture-51-ATEL-MALI.xlsx
 * Reproduction fidèle de la facture papier N°51 (ENT TECHNOLOGY / ATEL MALI).
 *
 * Usage (depuis api/) :
 *   php scripts/generate_facture_51_xlsx.php
 */

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

$outDir = dirname(__DIR__, 2).'/documentation/modeles';
if (!is_dir($outDir) && !mkdir($outDir, 0775, true) && !is_dir($outDir)) {
    fwrite(STDERR, "Impossible de créer $outDir\n");
    exit(1);
}
$outFile = $outDir.'/facture-51-ATEL-MALI.xlsx';

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Facture 51');

$sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
$sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
$sheet->getPageMargins()->setTop(0.7);
$sheet->getPageMargins()->setBottom(0.7);
$sheet->getPageMargins()->setLeft(0.7);
$sheet->getPageMargins()->setRight(0.7);
$sheet->getPageSetup()->setFitToPage(true);
$sheet->getPageSetup()->setFitToWidth(1);
$sheet->getPageSetup()->setFitToHeight(0);

$sheet->getColumnDimension('A')->setWidth(48);
$sheet->getColumnDimension('B')->setWidth(10);
$sheet->getColumnDimension('C')->setWidth(8);
$sheet->getColumnDimension('D')->setWidth(12);
$sheet->getColumnDimension('E')->setWidth(12);

$defaultFont = [
    'name' => 'Times New Roman',
    'size' => 11,
    'color' => ['rgb' => '000000'],
];
$spreadsheet->getDefaultStyle()->applyFromArray(['font' => $defaultFont]);

$thinBlack = [
    'borderStyle' => Border::BORDER_THIN,
    'color' => ['rgb' => '000000'],
];
$allBorders = [
    'borders' => [
        'allBorders' => $thinBlack,
    ],
];

// --- En-tête ---
$sheet->setCellValue('A1', 'ENT TECHNOLOGY');
$sheet->getStyle('A1')->applyFromArray([
    'font' => ['name' => 'Times New Roman', 'size' => 22, 'bold' => true],
]);
$sheet->setCellValue('A2', 'ACI 2000');
$sheet->getStyle('A2')->applyFromArray([
    'font' => ['name' => 'Times New Roman', 'size' => 11],
]);

$sheet->mergeCells('D1:E1');
$sheet->setCellValue('D1', 'Facture N°51');
$sheet->getStyle('D1')->applyFromArray([
    'font' => ['name' => 'Times New Roman', 'size' => 12, 'underline' => Font::UNDERLINE_SINGLE],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
]);

$sheet->setCellValue('D2', 'Facture');
$sheet->setCellValue('E2', 'Date');
$sheet->setCellValue('D3', '51');
$sheet->setCellValue('E3', '17/08/2026');
$sheet->getStyle('D2:E3')->applyFromArray(array_merge($allBorders, [
    'font' => ['name' => 'Times New Roman', 'size' => 10],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
]));
$sheet->getStyle('D2:E2')->getFont()->setBold(true);

// --- Bloc légal ---
$row = 5;
foreach ([
    '[Bamako, Mali]',
    'N° Matricule National Nina : 42509195397048P',
    'N° Fiscal 085157253V',
    'Phone : (+223) 74 50 45 92 / 50 30 70 70',
    'Ouattarahamidou@gmail.com',
] as $line) {
    $sheet->mergeCells("A{$row}:E{$row}");
    $sheet->setCellValue("A{$row}", $line);
    $sheet->getStyle("A{$row}")->applyFromArray([
        'font' => ['name' => 'Times New Roman', 'size' => 10],
    ]);
    ++$row;
}

// --- BILL TO ---
++$row;
$sheet->mergeCells("A{$row}:E{$row}");
$sheet->setCellValue("A{$row}", 'BILL TO');
$sheet->getStyle("A{$row}")->applyFromArray([
    'font' => ['name' => 'Times New Roman', 'size' => 11, 'bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '4A4A4A'],
    ],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
]);
$sheet->getRowDimension($row)->setRowHeight(20);
++$row;
$sheet->mergeCells("A{$row}:E{$row}");
$sheet->setCellValue("A{$row}", 'ATEL MALI');
$sheet->getStyle("A{$row}")->applyFromArray([
    'font' => ['name' => 'Times New Roman', 'size' => 11],
]);

// --- Information sur le Service ---
++$row;
$sheet->mergeCells("A{$row}:E{$row}");
$sheet->setCellValue("A{$row}", 'Information sur le Service');
$sheet->getStyle("A{$row}")->applyFromArray([
    'font' => ['name' => 'Times New Roman', 'size' => 11, 'bold' => true, 'color' => ['rgb' => '000000']],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '9A9A9A'],
    ],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
]);
$sheet->getRowDimension($row)->setRowHeight(20);
++$row;
$sheet->mergeCells("A{$row}:E{$row}");
$sheet->setCellValue("A{$row}", 'HAMDALLAYE ACI 2000, Immeuble TELECEL');
++$row;
$sheet->mergeCells("A{$row}:E{$row}");
$sheet->setCellValue("A{$row}", 'BP 2842 Bamako -');

// --- Project ---
$row += 2;
$sheet->mergeCells("A{$row}:E{$row}");
$sheet->setCellValue("A{$row}", 'Project : Protection des panneaux solaire');
$sheet->getStyle("A{$row}")->applyFromArray([
    'font' => ['name' => 'Times New Roman', 'size' => 11, 'bold' => true],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);

// --- Tableau lignes ---
$row += 2;
$headerRow = $row;
foreach (['Description', 'Unit', 'QTY', 'Prix/U', 'Montant'] as $i => $header) {
    $col = chr(ord('A') + $i);
    $sheet->setCellValue("{$col}{$row}", $header);
}
$sheet->getStyle("A{$row}:E{$row}")->applyFromArray(array_merge($allBorders, [
    'font' => ['name' => 'Times New Roman', 'size' => 10, 'bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '4A4A4A'],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
]));
$sheet->getRowDimension($row)->setRowHeight(18);

$lines = [
    ['La protection des panneaux solaire sur le site de SEG4244 San_Lafiabougou', 'Lot', '1', '120 000', '120 000'],
    ['Rouleau de files Galva', 'Lot', '3', '1 500', '4 500'],
    ['Cornaire', 'Lot', '10', '7 000', '70 000'],
    ['1 Cardina', 'Lot', '1', '2 500', '2 500'],
    ["Main d'œuvre, soudure, installation barbelé", 'Lot', '1', '60 000', '60 000'],
];

foreach ($lines as $line) {
    ++$row;
    $sheet->setCellValue("A{$row}", $line[0]);
    $sheet->setCellValue("B{$row}", $line[1]);
    $sheet->setCellValue("C{$row}", $line[2]);
    $sheet->setCellValue("D{$row}", $line[3]);
    $sheet->setCellValue("E{$row}", $line[4]);
    $sheet->getStyle("A{$row}:E{$row}")->applyFromArray(array_merge($allBorders, [
        'font' => ['name' => 'Times New Roman', 'size' => 10],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
    ]));
    $sheet->getStyle("B{$row}:C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("D{$row}:E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle("A{$row}")->getAlignment()->setWrapText(true);
    $sheet->getRowDimension($row)->setRowHeight(-1);
}

++$row;
$sheet->mergeCells("A{$row}:D{$row}");
$sheet->setCellValue("A{$row}", 'TOTAL');
$sheet->setCellValue("E{$row}", '257 000');
$sheet->getStyle("A{$row}:E{$row}")->applyFromArray(array_merge($allBorders, [
    'font' => ['name' => 'Times New Roman', 'size' => 10, 'bold' => true],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_RIGHT,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
]));

// --- Montant en lettres ---
$row += 2;
$sheet->mergeCells("A{$row}:E{$row}");
$sheet->setCellValue(
    "A{$row}",
    'Arrêté la présente facture à la somme de : Deux Cent Cinquante Sept Mille ( 257 000 ) Franc CFA'
);
$sheet->getStyle("A{$row}")->applyFromArray([
    'font' => ['name' => 'Times New Roman', 'size' => 10],
    'alignment' => ['wrapText' => true],
]);
$sheet->getRowDimension($row)->setRowHeight(30);

// --- Paiement / footer ---
$row += 2;
$sheet->mergeCells("A{$row}:E{$row}");
$sheet->setCellValue("A{$row}", "Veuillez Paye à l'ordre de Mr Hamidou OUTTARA");
$sheet->getStyle("A{$row}")->applyFromArray([
    'font' => ['name' => 'Times New Roman', 'size' => 11],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);

++$row;
$sheet->mergeCells("A{$row}:E{$row}");
$sheet->setCellValue("A{$row}", '[ENT, 74 50 45 92 / 50 30 70 70 ouattarahamidou2@gmail.com]');
$sheet->getStyle("A{$row}")->applyFromArray([
    'font' => [
        'name' => 'Times New Roman',
        'size' => 10,
        'bold' => true,
        'underline' => Font::UNDERLINE_SINGLE,
    ],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);

$sheet->getStyle("A1:E{$row}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

$writer = new Xlsx($spreadsheet);
$writer->save($outFile);

echo "Wrote {$outFile}\n";
