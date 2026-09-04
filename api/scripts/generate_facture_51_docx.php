<?php

/**
 * Génère documentation/modeles/facture-51-ATEL-MALI.docx
 * Reproduction fidèle de la facture papier N°51 (ENT TECHNOLOGY / ATEL MALI).
 *
 * Usage (depuis api/) :
 *   php scripts/generate_facture_51_docx.php
 */

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\TblWidth;
use PhpOffice\PhpWord\Style\Language;

$outDir = dirname(__DIR__, 2).'/documentation/modeles';
if (!is_dir($outDir) && !mkdir($outDir, 0775, true) && !is_dir($outDir)) {
    fwrite(STDERR, "Impossible de créer $outDir\n");
    exit(1);
}
$outFile = $outDir.'/facture-51-ATEL-MALI.docx';

$phpWord = new PhpWord();
$phpWord->getSettings()->setThemeFontLang(new Language(Language::FR_FR));
$phpWord->setDefaultFontName('Times New Roman');
$phpWord->setDefaultFontSize(11);

$section = $phpWord->addSection([
    'marginTop' => 1000,
    'marginBottom' => 1000,
    'marginLeft' => 1000,
    'marginRight' => 1000,
    'pageSizeW' => 11906,
    'pageSizeH' => 16838,
]);

$headerTable = $section->addTable(['width' => 100 * 50, 'unit' => TblWidth::PERCENT]);
$headerTable->addRow();
$left = $headerTable->addCell(5500);
$left->addText('ENT TECHNOLOGY', ['bold' => true, 'size' => 22, 'name' => 'Times New Roman']);
$left->addText('ACI 2000', ['size' => 11, 'name' => 'Times New Roman']);

$right = $headerTable->addCell(4500);
$right->addText('Facture N°51', ['size' => 12, 'underline' => 'single', 'name' => 'Times New Roman'], ['alignment' => Jc::END]);

$meta = $right->addTable([
    'borderSize' => 6,
    'borderColor' => '000000',
    'width' => 2600,
    'unit' => TblWidth::TWIP,
    'alignment' => Jc::END,
]);
$meta->addRow();
$meta->addCell(1300, ['borderSize' => 6, 'borderColor' => '000000'])->addText('Facture', ['bold' => true, 'size' => 10], ['alignment' => Jc::CENTER]);
$meta->addCell(1300, ['borderSize' => 6, 'borderColor' => '000000'])->addText('Date', ['bold' => true, 'size' => 10], ['alignment' => Jc::CENTER]);
$meta->addRow();
$meta->addCell(1300, ['borderSize' => 6, 'borderColor' => '000000'])->addText('51', ['size' => 10], ['alignment' => Jc::CENTER]);
$meta->addCell(1300, ['borderSize' => 6, 'borderColor' => '000000'])->addText('17/08/2026', ['size' => 10], ['alignment' => Jc::CENTER]);

$section->addTextBreak(1);
foreach ([
    '[Bamako, Mali]',
    'N° Matricule National Nina : 42509195397048P',
    'N° Fiscal 085157253V',
    'Phone : (+223) 74 50 45 92 / 50 30 70 70',
    'Ouattarahamidou@gmail.com',
] as $line) {
    $section->addText($line, ['size' => 10, 'name' => 'Times New Roman']);
}

$section->addTextBreak(1);

$barStyle = ['bgColor' => '4A4A4A', 'borderSize' => 0];
$bar = $section->addTable(['width' => 100 * 50, 'unit' => TblWidth::PERCENT]);
$bar->addRow();
$bar->addCell(10000, $barStyle)->addText('BILL TO', ['bold' => true, 'color' => 'FFFFFF', 'size' => 11, 'name' => 'Times New Roman']);
$section->addText('ATEL MALI', ['size' => 11, 'name' => 'Times New Roman']);

$bar2 = $section->addTable(['width' => 100 * 50, 'unit' => TblWidth::PERCENT]);
$bar2->addRow();
$bar2->addCell(10000, ['bgColor' => '9A9A9A', 'borderSize' => 0])->addText(
    'Information sur le Service',
    ['bold' => true, 'color' => '000000', 'size' => 11, 'name' => 'Times New Roman']
);
$section->addText('HAMDALLAYE ACI 2000, Immeuble TELECEL', ['size' => 11, 'name' => 'Times New Roman']);
$section->addText('BP 2842 Bamako -', ['size' => 11, 'name' => 'Times New Roman']);

$section->addTextBreak(1);
$section->addText('Project : Protection des panneaux solaire', ['bold' => true, 'size' => 11, 'name' => 'Times New Roman'], ['alignment' => Jc::CENTER]);
$section->addTextBreak(1);

$cellBorder = ['borderSize' => 6, 'borderColor' => '000000', 'valign' => 'center'];
$headerCell = ['borderSize' => 6, 'borderColor' => '000000', 'bgColor' => '4A4A4A', 'valign' => 'center'];
$table = $section->addTable([
    'borderSize' => 6,
    'borderColor' => '000000',
    'width' => 100 * 50,
    'unit' => TblWidth::PERCENT,
]);

$table->addRow();
foreach (['Description', 'Unit', 'QTY', 'Prix/U', 'Montant'] as $h) {
    $table->addCell(null, $headerCell)->addText($h, ['bold' => true, 'color' => 'FFFFFF', 'size' => 10, 'name' => 'Times New Roman'], ['alignment' => Jc::CENTER]);
}

$lines = [
    ['La protection des panneaux solaire sur le site de SEG4244 San_Lafiabougou', 'Lot', '1', '120 000', '120 000'],
    ['Rouleau de files Galva', 'Lot', '3', '1 500', '4 500'],
    ['Cornaire', 'Lot', '10', '7 000', '70 000'],
    ['1 Cardina', 'Lot', '1', '2 500', '2 500'],
    ["Main d'œuvre, soudure, installation barbelé", 'Lot', '1', '60 000', '60 000'],
];

foreach ($lines as $row) {
    $table->addRow();
    $table->addCell(4800, $cellBorder)->addText($row[0], ['size' => 10, 'name' => 'Times New Roman']);
    $table->addCell(1000, $cellBorder)->addText($row[1], ['size' => 10, 'name' => 'Times New Roman'], ['alignment' => Jc::CENTER]);
    $table->addCell(1000, $cellBorder)->addText($row[2], ['size' => 10, 'name' => 'Times New Roman'], ['alignment' => Jc::CENTER]);
    $table->addCell(1600, $cellBorder)->addText($row[3], ['size' => 10, 'name' => 'Times New Roman'], ['alignment' => Jc::END]);
    $table->addCell(1600, $cellBorder)->addText($row[4], ['size' => 10, 'name' => 'Times New Roman'], ['alignment' => Jc::END]);
}

$table->addRow();
$totalCell = $table->addCell(8400, $cellBorder);
$totalCell->getStyle()->setGridSpan(4);
$totalCell->addText('TOTAL', ['bold' => true, 'size' => 10, 'name' => 'Times New Roman'], ['alignment' => Jc::END]);
$table->addCell(1600, $cellBorder)->addText('257 000', ['bold' => true, 'size' => 10, 'name' => 'Times New Roman'], ['alignment' => Jc::END]);

$section->addTextBreak(1);
$section->addText(
    'Arrêté la présente facture à la somme de : Deux Cent Cinquante Sept Mille ( 257 000 ) Franc CFA',
    ['size' => 10, 'name' => 'Times New Roman']
);

$section->addTextBreak(2);
$section->addText("Veuillez Paye à l'ordre de Mr Hamidou OUTTARA", ['size' => 11, 'name' => 'Times New Roman'], ['alignment' => Jc::CENTER]);
$section->addText(
    '[ENT, 74 50 45 92 / 50 30 70 70 ouattarahamidou2@gmail.com]',
    ['bold' => true, 'underline' => 'single', 'size' => 10, 'name' => 'Times New Roman'],
    ['alignment' => Jc::CENTER]
);

$writer = IOFactory::createWriter($phpWord, 'Word2007');
$writer->save($outFile);

echo "Wrote {$outFile}\n";
