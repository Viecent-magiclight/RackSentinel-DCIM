<?php
/**
 * Minimal Visio 2013+ (.vsdx) writer — OPC zip of XML parts, editable rectangle shapes.
 * No external dependencies beyond ZipArchive.
 */

function vsdx_xml(string $s): string
{
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function vsdx_status_fill(string $status): string
{
    $map = [
        'online'      => '#143D2E',
        'warning'     => '#3D2E12',
        'offline'     => '#2A1A22',
        'maintenance' => '#252A3D',
    ];
    return $map[$status] ?? '#1A2834';
}

/** Racks to export: same row as anchor (整列对比), or single rack if no row_label. */
function vsdx_racks_for_export(int $rackId): array
{
    $anchor = fetch_one('SELECT * FROM racks WHERE id = ?', [$rackId]);
    if (!$anchor) {
        throw new InvalidArgumentException('机柜不存在');
    }
    $rowLabel = trim((string) ($anchor['row_label'] ?? ''));
    $racks    = $rowLabel !== ''
        ? fetch_all(
            'SELECT * FROM racks WHERE room_id = ? AND row_label = ? ORDER BY name',
            [(int) $anchor['room_id'], $rowLabel]
        )
        : [$anchor];

    $out = [];
    foreach ($racks as $rack) {
        $id = (int) $rack['id'];
        $rack['stats']   = rack_stats($id);
        $rack['devices'] = fetch_all(
            'SELECT * FROM devices WHERE rack_id = ? ORDER BY u_start ASC',
            [$id]
        );
        $out[] = $rack;
    }
    return [
        'anchor'   => $anchor,
        'racks'    => $out,
        'rowLabel' => $rowLabel,
    ];
}

function vsdx_rect_shape(
    int $id,
    float $pinX,
    float $pinY,
    float $w,
    float $h,
    string $text,
    string $fill,
    string $line,
    float $fontPt = 8
): string {
    $locX = $w / 2;
    $locY = $h / 2;
    $text = vsdx_xml($text);
    $fill = vsdx_xml($fill);
    $line = vsdx_xml($line);
    return <<<XML
<Shape ID="{$id}" Type="Shape" LineStyle="3" FillStyle="3" TextStyle="3">
  <Cell N="PinX" U="IN" V="{$pinX}"/>
  <Cell N="PinY" U="IN" V="{$pinY}"/>
  <Cell N="Width" U="IN" V="{$w}"/>
  <Cell N="Height" U="IN" V="{$h}"/>
  <Cell N="LocPinX" U="IN" V="{$locX}"/>
  <Cell N="LocPinY" U="IN" V="{$locY}"/>
  <Cell N="Angle" U="DEG" V="0"/>
  <Cell N="FillForegnd" V="{$fill}"/>
  <Cell N="LineColor" V="{$line}"/>
  <Cell N="LineWeight" U="PT" V="0.5"/>
  <Cell N="VerticalAlign" V="1"/>
  <Cell N="TopMargin" U="PT" V="2"/>
  <Cell N="BottomMargin" U="PT" V="2"/>
  <Cell N="LeftMargin" U="PT" V="2"/>
  <Cell N="RightMargin" U="PT" V="2"/>
  <Cell N="Size" U="PT" V="{$fontPt}"/>
  <Text>{$text}</Text>
  <Section N="Geometry" IX="0">
    <Cell N="NoFill" V="0"/>
    <Cell N="NoLine" V="0"/>
    <Row T="MoveTo" IX="1"><Cell N="X" V="0"/><Cell N="Y" V="0"/></Row>
    <Row T="LineTo" IX="2"><Cell N="X" V="Width"/><Cell N="Y" V="0"/></Row>
    <Row T="LineTo" IX="3"><Cell N="X" V="Width"/><Cell N="Y" V="Height"/></Row>
    <Row T="LineTo" IX="4"><Cell N="X" V="0"/><Cell N="Y" V="Height"/></Row>
    <Row T="LineTo" IX="5"><Cell N="X" V="0"/><Cell N="Y" V="0"/></Row>
  </Section>
</Shape>
XML;
}

function vsdx_text_shape(int $id, float $pinX, float $pinY, float $w, float $h, string $text, float $fontPt = 11): string
{
    $text = vsdx_xml($text);
    $locX = $w / 2;
    $locY = $h / 2;
    return <<<XML
<Shape ID="{$id}" Type="Shape" LineStyle="3" FillStyle="3" TextStyle="3">
  <Cell N="PinX" U="IN" V="{$pinX}"/>
  <Cell N="PinY" U="IN" V="{$pinY}"/>
  <Cell N="Width" U="IN" V="{$w}"/>
  <Cell N="Height" U="IN" V="{$h}"/>
  <Cell N="LocPinX" U="IN" V="{$locX}"/>
  <Cell N="LocPinY" U="IN" V="{$locY}"/>
  <Cell N="FillPattern" V="0"/>
  <Cell N="LinePattern" V="0"/>
  <Cell N="CharColor" V="#EAF4FF"/>
  <Cell N="Size" U="PT" V="{$fontPt}"/>
  <Cell N="VerticalAlign" V="1"/>
  <Text>{$text}</Text>
</Shape>
XML;
}

function vsdx_build_page_shapes(array $racks, string $pageTitle): string
{
    $types    = device_types();
    $statuses = device_statuses();

    $uhIn     = 0.22;
    $rackW    = 1.75;
    $rackGap  = 0.35;
    $marginX  = 0.6;
    $marginY  = 0.5;
    $titleH   = 0.55;
    $headerH  = 0.45;
    $rackPad  = 0.08;

    $maxU = 42;
    foreach ($racks as $rack) {
        $maxU = max($maxU, (int) ($rack['u_total'] ?? 42));
    }
    $gridH = $maxU * $uhIn;

    $shapes = [];
    $sid    = 1;

    $pageW = $marginX * 2 + count($racks) * $rackW + (count($racks) - 1) * $rackGap;
    $pageH = $marginY + $titleH + $headerH + $gridH + $marginY;
    $topY  = $pageH - $marginY;

    $shapes[] = vsdx_text_shape(
        $sid++,
        $pageW / 2,
        $topY - $titleH / 2,
        min(12, $pageW - 0.4),
        $titleH,
        $pageTitle,
        14
    );

    $baseY = $topY - $titleH - $headerH - $gridH;

    foreach ($racks as $i => $rack) {
        $leftX   = $marginX + $i * ($rackW + $rackGap);
        $centerX = $leftX + $rackW / 2;
        $st      = $rack['stats'] ?? rack_stats((int) $rack['id']);
        $uTotal  = max(1, (int) ($rack['u_total'] ?? 42));
        $capKw   = round(((int) ($rack['power_capacity'] ?? 0)) / 1000, 1);
        $pwrKw   = round(($st['power'] ?? 0) / 1000, 1);

        $hdr = ($rack['name'] ?? 'Rack') . "\n"
            . ($rack['label'] ?? '') . '  '
            . ($st['used_u'] ?? 0) . '/' . $uTotal . 'U  '
            . $pwrKw . ' / ' . $capKw . ' kW';
        $shapes[] = vsdx_text_shape(
            $sid++,
            $centerX,
            $baseY + $gridH + $headerH / 2,
            $rackW,
            $headerH,
            trim($hdr),
            7
        );

        // Rack frame
        $shapes[] = vsdx_rect_shape(
            $sid++,
            $centerX,
            $baseY + $gridH / 2,
            $rackW,
            $gridH,
            '',
            '#0A1218',
            '#3D5A6E',
            6
        );

        // U rulers (every 5U tick on left strip)
        for ($u = 1; $u <= $uTotal; $u++) {
            if ($u % 5 !== 0 && $u !== 1) {
                continue;
            }
            $uh   = $uhIn;
            $pinY = $baseY + ($u - 1) * $uhIn + $uh / 2;
            $shapes[] = vsdx_text_shape(
                $sid++,
                $leftX - 0.18,
                $pinY,
                0.28,
                $uh,
                (string) $u,
                6
            );
        }

        foreach ($rack['devices'] as $d) {
            $uStart = (int) $d['u_start'];
            $uSize  = max(1, (int) $d['u_size']);
            if ($uStart < 1) {
                continue;
            }
            $typeKey = (string) ($d['type_key'] ?? 'server');
            $tMeta   = $types[$typeKey] ?? $types['server'];
            $line    = (string) ($tMeta['color'] ?? '#35c6ff');
            $fill    = vsdx_status_fill((string) ($d['status'] ?? 'online'));
            if ($typeKey === 'blank') {
                $fill = '#1E2A34';
                $line = '#3b5468';
            }

            $h    = $uSize * $uhIn - 0.02;
            $pinY = $baseY + ($uStart - 1) * $uhIn + $h / 2;
            $w    = $rackW - $rackPad * 2;

            $statusName = $statuses[$d['status']]['name'] ?? (string) $d['status'];
            $label      = (string) ($d['name'] ?? '');
            if ($uSize >= 2) {
                $label .= "\n" . ($tMeta['name'] ?? $typeKey);
                if ($uSize >= 3 && !empty($d['hostname'])) {
                    $label .= "\n" . (string) $d['hostname'];
                }
            } else {
                $label .= ' · ' . ($tMeta['name'] ?? $typeKey);
            }
            $label .= "\nU{$uStart}" . ($uSize > 1 ? '-' . ($uStart + $uSize - 1) : '') . ' · ' . $statusName;

            $font = $uSize <= 1 ? 6 : ($uSize <= 2 ? 7 : 8);
            $shapes[] = vsdx_rect_shape($sid++, $centerX, $pinY, $w, $h, $label, $fill, $line, $font);
        }
    }

    $body = implode("\n", $shapes);
    $pageW = number_format($pageW, 4, '.', '');
    $pageH = number_format($pageH, 4, '.', '');
    $cx    = number_format((float) $pageW / 2, 4, '.', '');
    $cy    = number_format((float) $pageH / 2, 4, '.', '');

    return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<PageContents xmlns="http://schemas.microsoft.com/office/visio/2012/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xml:space="preserve">
  <PageSheet LineStyle="0" FillStyle="0" TextStyle="0">
    <Cell N="PageWidth" U="IN" V="{$pageW}"/>
    <Cell N="PageHeight" U="IN" V="{$pageH}"/>
    <Cell N="PageScale" U="MM" V="1"/>
    <Cell N="DrawingScale" U="MM" V="1"/>
    <Cell N="DrawingSizeType" V="0"/>
    <Cell N="DrawingScaleType" V="0"/>
    <Cell N="InhibitSnap" V="0"/>
    <Cell N="UIVisibility" V="0"/>
    <Cell N="ShdwOffsetX" U="IN" V="0.125"/>
    <Cell N="ShdwOffsetY" U="IN" V="-0.125"/>
    <Cell N="PageLockReplace" V="0"/>
    <Cell N="PageLockDuplicate" V="0"/>
    <Cell N="PrintPageOrientation" V="2"/>
  </PageSheet>
  <Shapes>
{$body}
  </Shapes>
</PageContents>
XML;
}

function vsdx_package_xml(string $pageXml, string $pageName = 'RackRow'): string
{
    $pageName = vsdx_xml($pageName);
    $now      = gmdate('Y-m-d\TH:i:s\Z');

    $contentTypes = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/visio/document.xml" ContentType="application/vnd.ms-visio.drawing.main+xml"/>
  <Override PartName="/visio/pages/pages.xml" ContentType="application/vnd.ms-visio.pages+xml"/>
  <Override PartName="/visio/pages/page1.xml" ContentType="application/vnd.ms-visio.page+xml"/>
  <Override PartName="/visio/windows.xml" ContentType="application/vnd.ms-visio.windows+xml"/>
  <Override PartName="/visio/theme/theme1.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
</Types>
XML;

    $rootRels = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.microsoft.com/visio/2010/relationships/document" Target="visio/document.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>
XML;

    $docRels = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.microsoft.com/visio/2010/relationships/pages" Target="pages/pages.xml"/>
  <Relationship Id="rId2" Type="http://schemas.microsoft.com/visio/2010/relationships/windows" Target="windows.xml"/>
  <Relationship Id="rId3" Type="http://schemas.microsoft.com/visio/2010/relationships/theme" Target="theme/theme1.xml"/>
</Relationships>
XML;

    $pagesRels = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.microsoft.com/visio/2010/relationships/page" Target="page1.xml"/>
</Relationships>
XML;

    $document = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<VisioDocument xmlns="http://schemas.microsoft.com/office/visio/2012/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xml:space="preserve">
  <DocumentSettings/>
  <Colors>
    <ColorEntry IX="24" RGB="#FF0000"/>
  </Colors>
  <FaceNames/>
  <StyleSheets>
    <StyleSheet ID="0" NameU="No Style" IsCustomNameU="1" Name="No Style" IsCustomName="1">
      <Cell N="LineStyle" V="0"/>
      <Cell N="FillStyle" V="0"/>
      <Cell N="TextStyle" V="0"/>
    </StyleSheet>
    <StyleSheet ID="3" NameU="Connector" IsCustomNameU="1" Name="Connector" IsCustomName="1">
      <Cell N="LineStyle" V="3"/>
      <Cell N="FillStyle" V="3"/>
      <Cell N="TextStyle" V="3"/>
    </StyleSheet>
  </StyleSheets>
  <DocumentSheet NameU="TheDoc" IsCustomNameU="1">
    <Cell N="DocLangID" V="2052"/>
  </DocumentSheet>
  <Masters/>
  <Pages>
    <Page ID="0" NameU="{$pageName}" Name="{$pageName}" ViewScale="1" ViewCenterX="4" ViewCenterY="5.5">
      <PageSheet LineStyle="0" FillStyle="0" TextStyle="0">
        <Cell N="PageWidth" U="IN" V="16"/>
        <Cell N="PageHeight" U="IN" V="11"/>
      </PageSheet>
      <Rel r:id="rId1"/>
    </Page>
  </Pages>
</VisioDocument>
XML;

    $pages = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Pages xmlns="http://schemas.microsoft.com/office/visio/2012/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xml:space="preserve">
  <Page ID="0" NameU="{$pageName}" Name="{$pageName}" ViewScale="1" ViewCenterX="4" ViewCenterY="5.5">
    <PageSheet LineStyle="0" FillStyle="0" TextStyle="0">
      <Cell N="PageWidth" U="IN" V="16"/>
      <Cell N="PageHeight" U="IN" V="11"/>
    </PageSheet>
    <Rel r:id="rId1"/>
  </Page>
</Pages>
XML;

    $windows = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Windows xmlns="http://schemas.microsoft.com/office/visio/2012/main">
  <Window ID="0" WindowType="Drawing" WindowState="1073741824" WindowLeft="100" WindowTop="100" WindowWidth="1200" WindowHeight="800">
    <PageTab ID="0"/>
  </Window>
</Windows>
XML;

    $theme = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="Office Theme">
  <a:themeElements>
    <a:clrScheme name="Office">
      <a:dk1><a:sysClr val="windowText" lastClr="000000"/></a:dk1>
      <a:lt1><a:sysClr val="window" lastClr="FFFFFF"/></a:lt1>
      <a:dk2><a:srgbClr val="44546A"/></a:dk2>
      <a:lt2><a:srgbClr val="E7E6E6"/></a:lt2>
      <a:accent1><a:srgbClr val="4472C4"/></a:accent1>
      <a:accent2><a:srgbClr val="ED7D31"/></a:accent2>
      <a:accent3><a:srgbClr val="A5A5A5"/></a:accent3>
      <a:accent4><a:srgbClr val="FFC000"/></a:accent4>
      <a:accent5><a:srgbClr val="5B9BD5"/></a:accent5>
      <a:accent6><a:srgbClr val="70AD47"/></a:accent6>
      <a:hlink><a:srgbClr val="0563C1"/></a:hlink>
      <a:folHlink><a:srgbClr val="954F72"/></a:folHlink>
    </a:clrScheme>
    <a:fontScheme name="Office"><a:majorFont/><a:minorFont/></a:fontScheme>
    <a:fmtScheme name="Office"><a:fillStyleLst/><a:lnStyleLst/><a:effectStyleLst/><a:bgFillStyleLst/></a:fmtScheme>
  </a:themeElements>
</a:theme>
XML;

    $core = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <dc:title>RackSentinel rack export</dc:title>
  <dc:creator>RackSentinel DCIM</dc:creator>
  <cp:lastModifiedBy>RackSentinel DCIM</cp:lastModifiedBy>
  <dcterms:created xsi:type="dcterms:W3CDTF">{$now}</dcterms:created>
  <dcterms:modified xsi:type="dcterms:W3CDTF">{$now}</dcterms:modified>
</cp:coreProperties>
XML;

    $app = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
  <Application>RackSentinel DCIM</Application>
  <AppVersion>16.0000</AppVersion>
</Properties>
XML;

    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('PHP ZipArchive extension required for VSDX export');
    }
    $tmp = tempnam(sys_get_temp_dir(), 'vsdx');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Cannot create VSDX archive');
    }
    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addFromString('_rels/.rels', $rootRels);
    $zip->addFromString('docProps/core.xml', $core);
    $zip->addFromString('docProps/app.xml', $app);
    $zip->addFromString('visio/document.xml', $document);
    $zip->addFromString('visio/_rels/document.xml.rels', $docRels);
    $zip->addFromString('visio/pages/pages.xml', $pages);
    $zip->addFromString('visio/pages/_rels/pages.xml.rels', $pagesRels);
    $zip->addFromString('visio/pages/page1.xml', $pageXml);
    $zip->addFromString('visio/windows.xml', $windows);
    $zip->addFromString('visio/theme/theme1.xml', $theme);
    $zip->close();

    return $tmp;
}

function vsdx_build_row_file(int $rackId): string
{
    $bundle = vsdx_racks_for_export($rackId);
    $anchor = $bundle['anchor'];
    $racks  = $bundle['racks'];
    $title  = ($anchor['name'] ?? 'Rack') . ' · ' . ($anchor['label'] ?? '机柜');
    if ($bundle['rowLabel'] !== '') {
        $title = $bundle['rowLabel'] . ' — ' . count($racks) . ' 台机柜（'
            . implode('、', array_column($racks, 'name')) . '）';
    }
    $title .= "\nRackSentinel DCIM · " . date('Y-m-d H:i');

    $pageXml = vsdx_build_page_shapes($racks, $title);
    $pageName = preg_replace('/[^\w\-]+/u', '_', (string) ($anchor['name'] ?? 'Rack')) ?: 'RackRow';

    return vsdx_package_xml($pageXml, $pageName);
}

function vsdx_download(string $filename, int $rackId): void
{
    $path = vsdx_build_row_file($rackId);
    header('Content-Type: application/vnd.ms-visio.drawing');
    header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: no-store');
    readfile($path);
    @unlink($path);
    exit;
}
