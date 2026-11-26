<?php

declare(strict_types=1);

function xlsxColumnName(int $index): string
{
    $name = '';
    $index++;
    while ($index > 0) {
        $mod = ($index - 1) % 26;
        $name = chr(65 + $mod) . $name;
        $index = intdiv($index - 1, 26);
    }
    return $name;
}

function xlsxXml(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function exportRowsToXlsx(string $filename, array $headers, array $rows): void
{
    if (!class_exists('ZipArchive')) {
        // Fallback: CSV compatible con Excel (UTF-8 BOM)
        $csvName = preg_replace('/\.xlsx$/i', '.csv', $filename);
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $csvName);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $safeName . '"');
        header('Cache-Control: max-age=0, no-store');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM para Excel
        fputcsv($out, $headers, ';');
        foreach ($rows as $row) {
            $line = array_map(static fn($v) => (string) ($v ?? ''), $row);
            fputcsv($out, $line, ';');
        }
        fclose($out);
        exit;
    }

    $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
    if ($tmp === false) {
        throw new RuntimeException('No se pudo crear archivo temporal.');
    }

    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('No se pudo crear el archivo XLSX.');
    }

    $allRows = array_merge([$headers], $rows);
    $sheetRows = '';
    $rowNum = 1;
    foreach ($allRows as $row) {
        $sheetRows .= '<row r="' . $rowNum . '">';
        $colNum = 0;
        foreach ($row as $cell) {
            $cellRef = xlsxColumnName($colNum) . $rowNum;
            $value = (string) ($cell ?? '');
            // Usar inlineStr para evitar problemas con tipos
            $sheetRows .= '<c r="' . $cellRef . '" t="inlineStr"><is><t xml:space="preserve">' . xlsxXml($value) . '</t></is></c>';
            $colNum++;
        }
        $sheetRows .= '</row>';
        $rowNum++;
    }

    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheetData>' . $sheetRows . '</sheetData></worksheet>';

    $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Control Diario" sheetId="1" r:id="rId1"/></sheets></workbook>';

    $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
        . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
        . '</styleSheet>';

    // Rebuild sheet with header style (first row bold)
    $sheetRows = '';
    $rowNum = 1;
    foreach ($allRows as $row) {
        $sheetRows .= '<row r="' . $rowNum . '">';
        $colNum = 0;
        foreach ($row as $cell) {
            $cellRef = xlsxColumnName($colNum) . $rowNum;
            $value = (string) ($cell ?? '');
            $style = $rowNum === 1 ? ' s="1"' : '';
            $sheetRows .= '<c r="' . $cellRef . '" t="inlineStr"' . $style . '><is><t xml:space="preserve">' . xlsxXml($value) . '</t></is></c>';
            $colNum++;
        }
        $sheetRows .= '</row>';
        $rowNum++;
    }
    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetData>' . $sheetRows . '</sheetData></worksheet>';

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>';

    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';

    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addFromString('_rels/.rels', $rels);
    $zip->addFromString('xl/workbook.xml', $workbookXml);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);
    $zip->addFromString('xl/styles.xml', $stylesXml);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->close();

    $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $safeName . '"');
    header('Content-Length: ' . filesize($tmp));
    header('Cache-Control: max-age=0, no-store');
    readfile($tmp);
    @unlink($tmp);
    exit;
}
