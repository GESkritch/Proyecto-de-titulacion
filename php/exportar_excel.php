<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="registro.xlsx"');

require_admin_session_json();

$data = json_decode(file_get_contents('php://input'), true);
$fechaInicio = isset($data['fecha_inicio']) ? trim((string)$data['fecha_inicio']) : '';
$fechaFin = isset($data['fecha_fin']) ? trim((string)$data['fecha_fin']) : '';

if (!$fechaInicio || !$fechaFin) {
    http_response_code(400);
    echo json_encode(['error' => 'Rango de fechas requerido']);
    exit;
}

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    echo json_encode(['error' => 'La extensión ZipArchive no está disponible']);
    exit;
}

$db = get_db();
$stmt = $db->prepare('SELECT * FROM personas_listas WHERE fecha >= :inicio AND fecha <= :fin ORDER BY atendido_at DESC');
$stmt->execute([':inicio' => $fechaInicio, ':fin' => $fechaFin]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$dir = __DIR__ . '/../data';
if (!is_dir($dir)) @mkdir($dir, 0755, true);
$zipPath = $dir . '/registro_temp.xlsx';
$sheetPath = $dir . '/sheet1.xml';

$rowsData = [];
$rowsData[] = ['id', 'rut', 'nombre', 'apellido', 'correo', 'telefono', 'fecha', 'hora', 'tipo_tramite', 'estado', 'created_at', 'atendido_at'];
foreach ($rows as $row) {
    $rowsData[] = [
        $row['id'] ?? '',
        $row['rut'] ?? '',
        $row['nombre'] ?? '',
        $row['apellido'] ?? '',
        $row['correo'] ?? '',
        $row['telefono'] ?? '',
        $row['fecha'] ?? '',
        $row['hora'] ?? '',
        $row['tipo_tramite'] ?? '',
        $row['estado'] ?? '',
        $row['created_at'] ?? '',
        $row['atendido_at'] ?? '',
    ];
}

function column_letter($index) {
    $letter = '';
    while ($index > 0) {
        $index--; 
        $letter = chr(65 + ($index % 26)) . $letter;
        $index = intdiv($index, 26);
    }
    return $letter;
}

$xml = new SimpleXMLElement('<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData/></worksheet>');
$sheetData = $xml->sheetData;

foreach ($rowsData as $rowIndex => $row) {
    $rowNode = $sheetData->addChild('row');
    $rowNode->addAttribute('r', (string)($rowIndex + 1));
    foreach ($row as $colIndex => $value) {
        $cell = $rowNode->addChild('c');
        $cell->addAttribute('r', column_letter($colIndex + 1) . ($rowIndex + 1));
        $cell->addAttribute('t', 'inlineStr');
        $is = $cell->addChild('is');
        $text = $is->addChild('t', (string)$value);
        $text->addAttribute('xml:space', 'preserve');
    }
}

$xml->asXML($sheetPath);

$zip = new ZipArchive();
$zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$zip->addFile($sheetPath, 'xl/worksheets/sheet1.xml');
$zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Registro" sheetId="1" r:id="rId1"/></sheets></workbook>');
$zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
$zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
$zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
$zip->addFromString('docProps/app.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>Microsoft Excel</Application></Properties>');
$zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>Registro</dc:title><dc:creator>Panel Admin</dc:creator></cp:coreProperties>');
$zip->close();

ob_clean();
readfile($zipPath);
unlink($zipPath);
unlink($sheetPath);
