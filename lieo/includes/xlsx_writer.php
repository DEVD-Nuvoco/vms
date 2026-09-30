<?php
/**
 * Minimal .xlsx writer (no external library — ZipArchive is a core PHP
 * extension). Single sheet: bold header row, all cells bordered, column
 * widths sized to the longest value in each column.
 */
function lieo_export_xlsx(string $filename, array $headers, array $rows): void
{
    $colCount = count($headers);
    $widths = array_fill(0, $colCount, 0);
    $measure = static function ($v) { return mb_strlen((string) $v); };
    foreach ($headers as $i => $h) {
        $widths[$i] = max($widths[$i], $measure($h));
    }
    foreach ($rows as $row) {
        foreach (array_values($row) as $i => $v) {
            if ($i < $colCount) {
                $widths[$i] = max($widths[$i], $measure($v));
            }
        }
    }

    $esc = static function ($v) {
        return htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    };

    $cols = '<cols>';
    foreach ($widths as $i => $w) {
        $width = min(max($w + 2, 8), 60);
        $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $width . '" customWidth="1"/>';
    }
    $cols .= '</cols>';

    $colLetter = static function (int $i): string {
        $s = '';
        $i++;
        while ($i > 0) {
            $m = ($i - 1) % 26;
            $s = chr(65 + $m) . $s;
            $i = intdiv($i - 1, 26);
        }
        return $s;
    };

    $sheetRows = '<row r="1" s="1">';
    foreach ($headers as $i => $h) {
        $sheetRows .= '<c r="' . $colLetter($i) . '1" t="inlineStr" s="1"><is><t xml:space="preserve">' . $esc($h) . '</t></is></c>';
    }
    $sheetRows .= '</row>';
    $r = 2;
    foreach ($rows as $row) {
        $sheetRows .= '<row r="' . $r . '">';
        foreach (array_values($row) as $i => $v) {
            if ($i >= $colCount) {
                break;
            }
            $sheetRows .= '<c r="' . $colLetter($i) . $r . '" t="inlineStr" s="2"><is><t xml:space="preserve">' . $esc($v) . '</t></is></c>';
        }
        $sheetRows .= '</row>';
        $r++;
    }

    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . $cols
        . '<sheetData>' . $sheetRows . '</sheetData>'
        . '</worksheet>';

    $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
        . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
        . '<borders count="2">'
        . '<border><left/><right/><top/><bottom/><diagonal/></border>'
        . '<border><left style="thin"><color indexed="64"/></left><right style="thin"><color indexed="64"/></right>'
        . '<top style="thin"><color indexed="64"/></top><bottom style="thin"><color indexed="64"/></bottom><diagonal/></border>'
        . '</borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="3">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"/>'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>'
        . '</cellXfs>'
        . '</styleSheet>';

    $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Applications" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>';

    $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';

    $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>';

    $tmp = tempnam(sys_get_temp_dir(), 'lieoxlsx');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::OVERWRITE);
    $zip->addEmptyDir('_rels');
    $zip->addFromString('_rels/.rels', $rootRels);
    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addEmptyDir('xl');
    $zip->addFromString('xl/workbook.xml', $workbookXml);
    $zip->addEmptyDir('xl/_rels');
    $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
    $zip->addFromString('xl/styles.xml', $stylesXml);
    $zip->addEmptyDir('xl/worksheets');
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->close();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    unlink($tmp);
    exit;
}
