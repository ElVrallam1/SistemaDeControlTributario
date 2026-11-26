<?php
declare(strict_types=1);

function importReadFile(string $path, int $maxRows = 10000): array {
    $fh = fopen($path, 'rb');
    $magic = $fh ? fread($fh, 4) : '';
    if ($fh) fclose($fh);
    if ($magic === "PK\x03\x04" && class_exists('ZipArchive')) {
        return importReadXlsx($path, $maxRows);
    }
    return importReadCsv($path, $maxRows);
}

function importReadCsv(string $path, int $maxRows = 10000): array {
    $content = file_get_contents($path);
    if ($content === false || $content === '') return ['ok'=>false,'error'=>'No se pudo leer el archivo.'];
    // strip UTF-8 BOM
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
    $content = str_replace(["\r\n", "\r"], "\n", $content);
    $lines = array_values(array_filter(explode("\n", $content), fn($l)=>trim($l) !== ''));
    if (!$lines) return ['ok'=>false,'error'=>'El archivo está vacío.'];
    $first = $lines[0];
    $delim = (substr_count($first, ';') > substr_count($first, ',')) ? ';' : ',';
    $rows = [];
    foreach ($lines as $i => $line) {
        $rows[] = str_getcsv($line, $delim);
        if (count($rows) > $maxRows + 1) return ['ok'=>false,'error'=>"El archivo excede el máximo de {$maxRows} filas."];
    }
    $headers = array_map(fn($h)=>importNormalizeHeader((string)$h), array_shift($rows));
    $out = [];
    foreach ($rows as $idx => $r) {
        $assoc = [];
        foreach ($headers as $j => $h) { $assoc[$h] = isset($r[$j]) ? trim((string)$r[$j]) : ''; }
        $assoc['_row_num'] = $idx + 2;
        $out[] = $assoc;
    }
    return ['ok'=>true,'headers'=>$headers,'rows'=>$out,'delimiter'=>$delim];
}

function importReadXlsx(string $path, int $maxRows = 10000): array {
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return ['ok'=>false,'error'=>'No se pudo abrir el archivo XLSX.'];
    $ss = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml !== false) {
        $sx = @simplexml_load_string($ssXml);
        if ($sx && isset($sx->si)) {
            foreach ($sx->si as $si) {
                if (isset($si->t)) { $ss[] = (string)$si->t; continue; }
                $text = '';
                foreach ($si->r as $run) { $text .= (string)$run->t; }
                $ss[] = $text;
            }
        }
    }
    $sheetName = 'xl/worksheets/sheet1.xml';
    $wbXml = $zip->getFromName('xl/workbook.xml');
    if ($wbXml !== false) {
        $wb = @simplexml_load_string($wbXml);
        if ($wb) {
            $wb->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
            $ridToTarget = [];
            if ($rels !== false) {
                $rx = @simplexml_load_string($rels);
                if ($rx) foreach ($rx->Relationship as $rel) { $ridToTarget[(string)$rel['Id']] = (string)$rel['Target']; }
            }
            $sheets = $wb->xpath('//m:sheet');
            if ($sheets && isset($sheets[0])) {
                $sheets[0]->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                $rid = (string)$sheets[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;
                if ($rid && isset($ridToTarget[$rid])) $sheetName = 'xl/' . ltrim($ridToTarget[$rid], '/');
            }
        }
    }
    $sheetXml = $zip->getFromName($sheetName);
    $zip->close();
    if ($sheetXml === false) return ['ok'=>false,'error'=>'No se pudo leer la hoja del XLSX.'];
    $sx = @simplexml_load_string($sheetXml);
    if (!$sx) return ['ok'=>false,'error'=>'XLSX inválido.'];
    $sx->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $rowsXml = $sx->xpath('//m:sheetData/m:row');
    if (!$rowsXml) return ['ok'=>false,'error'=>'El archivo XLSX está vacío.'];
    $grid = [];
    foreach ($rowsXml as $row) {
        $rnum = (int)$row['r'];
        $cells = [];
        foreach ($row->c as $c) {
            $ref = (string)$c['r'];
            preg_match('/([A-Z]+)/', $ref, $m);
            $col = importColIndex($m[1] ?? 'A');
            $t = (string)$c['t'];
            $v = isset($c->v) ? (string)$c->v : '';
            if ($t === 's') $val = $ss[(int)$v] ?? '';
            elseif ($t === 'inlineStr') $val = (string)$c->is->t;
            elseif ($t === 'b') $val = $v === '1' ? '1' : '0';
            else $val = $v;
            $cells[$col] = trim((string)$val);
        }
        if ($cells) { ksort($cells); $grid[$rnum] = $cells; }
        if (count($grid) > $maxRows + 1) return ['ok'=>false,'error'=>"El archivo excede el máximo de {$maxRows} filas."];
    }
    if (!$grid) return ['ok'=>false,'error'=>'El archivo XLSX está vacío.'];
    ksort($grid);
    $firstRow = array_shift($grid);
    $maxCol = max(array_keys($firstRow));
    foreach ($grid as $r) { if ($r) $maxCol = max($maxCol, max(array_keys($r))); }
    $headers = [];
    for ($i=0; $i<=$maxCol; $i++) $headers[] = importNormalizeHeader((string)($firstRow[$i] ?? 'col_'.($i+1)));
    $out = [];
    $rowNum = 2;
    foreach ($grid as $r) {
        $assoc = [];
        $allEmpty = true;
        for ($i=0; $i<=$maxCol; $i++) { $val = (string)($r[$i] ?? ''); $assoc[$headers[$i]] = $val; if ($val !== '') $allEmpty = false; }
        if ($allEmpty) { $rowNum++; continue; }
        $assoc['_row_num'] = $rowNum;
        $out[] = $assoc;
        $rowNum++;
    }
    return ['ok'=>true,'headers'=>$headers,'rows'=>$out,'delimiter'=>','];
}

function importColIndex(string $col): int {
    $col = strtoupper($col); $n = 0;
    for ($i=0; $i<strlen($col); $i++) { $n = $n * 26 + (ord($col[$i]) - 64); }
    return max(0, $n - 1);
}

function importNormalizeHeader(string $h): string {
    $h = trim($h);
    $h = preg_replace('/^\xEF\xBB\xBF/', '', $h);
    $h = mb_strtolower($h, 'UTF-8');
    $trans = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u'];
    $h = strtr($h, $trans);
    $h = preg_replace('/[^a-z0-9]+/', '_', $h);
    $h = trim($h, '_');
    return $h;
}

function importVal(array $row, array $keys, string $default=''): string {
    foreach ($keys as $k) { $nk = importNormalizeHeader($k); if (array_key_exists($nk, $row) && $row[$nk] !== '') return (string)$row[$nk]; }
    return $default;
}

function importInt(array $row, array $keys): int {
    $v = importVal($row, $keys, '0');
    $v = str_replace(['.', ' ', ','], ['', '', '.'], $v); // handle 1.234,56 -> 1234.56 naive? better simple
    if (preg_match('/^-?\d+$/', trim($v))) return max(0, (int)$v);
    $v2 = preg_replace('/[^0-9\-]/', '', $v);
    return max(0, (int)$v2);
}

function importFloat(array $row, array $keys): float {
    $v = trim(importVal($row, $keys, '0'));
    if ($v === '') return 0.0;
    // accept 1.234,56 or 1234.56
    if (preg_match('/^-?\d{1,3}(\.\d{3})*,\d+$/', $v)) { $v = str_replace('.', '', $v); $v = str_replace(',', '.', $v); }
    elseif (preg_match('/^-?\d+,\d+$/', $v)) { $v = str_replace(',', '.', $v); }
    return max(0.0, (float)$v);
}

function importDate(array $row, array $keys): ?string {
    $v = trim(importVal($row, $keys, ''));
    if ($v === '') return null;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return $v;
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $v, $m)) return $m[3].'-'.$m[2].'-'.$m[1];
    if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $v, $m)) return $m[3].'-'.$m[2].'-'.$m[1];
    // Excel serial date
    if (is_numeric($v) && (float)$v > 20000 && (float)$v < 60000) {
        $dt = (new DateTime('1899-12-30'))->modify('+'.((int)$v).' days');
        return $dt->format('Y-m-d');
    }
    return null;
}
