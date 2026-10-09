<?php
/**
 * SVG 图标雪碧图。所有页面 include 一次，之后用 <use href="#ico-xxx"> 引用。
 * 所有图标 viewBox 统一 0 0 24 24，描边类图标使用 currentColor，可直接用 CSS color 换色。
 */
?>
<svg xmlns="http://www.w3.org/2000/svg" style="position:absolute;width:0;height:0;overflow:hidden" aria-hidden="true">
<defs>
<g id="_stroke-defaults" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"></g>
</defs>

<!-- ============ 设备类型 ============ -->

<symbol id="ico-server" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
  <rect x="2.5" y="4" width="19" height="6.4" rx="1.3"/>
  <rect x="2.5" y="13.6" width="19" height="6.4" rx="1.3"/>
  <circle cx="5.9" cy="7.2" r="1.05"/>
  <circle cx="5.9" cy="16.8" r="1.05"/>
  <path d="M10 7.2h8.4M10 16.8h8.4" stroke-dasharray="2.1 1.7"/>
</symbol>

<symbol id="ico-blade" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
  <rect x="2.3" y="3.4" width="19.4" height="17.2" rx="1.4"/>
  <path d="M6.2 6.4v11.2M9.5 6.4v11.2M12.8 6.4v11.2M16.1 6.4v11.2"/>
  <path d="M19.2 6.4v3.2"/>
  <circle cx="19.3" cy="16.6" r="1"/>
</symbol>

<symbol id="ico-switch" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
  <rect x="1.8" y="9.4" width="20.4" height="7.6" rx="1.4"/>
  <path d="M4.9 14.2h1.7M8.2 14.2h1.7M11.5 14.2h1.7M14.8 14.2h1.7M18.1 14.2h1.4"/>
  <path d="M4.9 11.7h11"/>
  <path d="M8.6 5.9h6.8"/>
  <path d="M13.5 4.2 15.4 5.9 13.5 7.6"/>
  <path d="M10.5 4.2 8.6 5.9 10.5 7.6"/>
</symbol>

<symbol id="ico-router" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
  <rect x="1.8" y="12.6" width="20.4" height="6.6" rx="3.2"/>
  <path d="M5.4 15.9h1.6M8.8 15.9h1.6M12.2 15.9h1.6M15.6 15.9h1.6M19 15.9h.9"/>
  <path d="M8.9 9.6 15.3 3.6"/>
  <path d="M15.3 7V3.6h-3.2"/>
  <path d="M15.1 9.6 8.7 3.6"/>
  <path d="M8.7 7V3.6h3.2"/>
</symbol>

<symbol id="ico-firewall" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round">
  <rect x="2.3" y="3.6" width="19.4" height="16.8" rx="1.2"/>
  <path d="M2.3 9.2h19.4M2.3 14.8h19.4"/>
  <path d="M8.6 3.6v5.6M15.4 3.6v5.6"/>
  <path d="M5.4 9.2v5.6M12 9.2v5.6M18.6 9.2v5.6"/>
  <path d="M8.6 14.8v5.6M15.4 14.8v5.6"/>
  <path d="M12 9.9c1.9 1.7 2.8 3.1 2.8 4.4a2.8 2.8 0 0 1-5.6 0c0-1.3.9-2.7 2.8-4.4z" fill="currentColor" stroke="none"/>
</symbol>

<symbol id="ico-gateway" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
  <rect x="1.6" y="8.6" width="6.4" height="6.8" rx="1.2"/>
  <rect x="16" y="8.6" width="6.4" height="6.8" rx="1.2"/>
  <path d="M8.8 12h6.4"/>
  <path d="M13.3 10.1 15.4 12l-2.1 1.9"/>
  <path d="M10.7 10.1 8.6 12l2.1 1.9"/>
  <path d="M4.8 8.6V5.4M19.2 15.4v3.2"/>
</symbol>

<symbol id="ico-lb" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
  <rect x="1.4" y="9.8" width="5.4" height="4.6" rx="1"/>
  <rect x="15.2" y="3.4" width="7.4" height="4.2" rx="1"/>
  <rect x="15.2" y="9.9" width="7.4" height="4.2" rx="1"/>
  <rect x="15.2" y="16.4" width="7.4" height="4.2" rx="1"/>
  <path d="M6.8 12.1h3.6"/>
  <path d="M10.4 12.1 14.8 5.5M10.4 12.1h4.4M10.4 12.1l4.4 6.4"/>
</symbol>

<symbol id="ico-storage" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
  <ellipse cx="12" cy="5.6" rx="8.4" ry="2.9"/>
  <path d="M3.6 5.6v4.3c0 1.6 3.8 2.9 8.4 2.9s8.4-1.3 8.4-2.9V5.6"/>
  <path d="M3.6 11.9v4.3c0 1.6 3.8 2.9 8.4 2.9s8.4-1.3 8.4-2.9v-4.3"/>
  <circle cx="16.8" cy="9.6" r=".85" fill="currentColor" stroke="none"/>
  <circle cx="16.8" cy="15.9" r=".85" fill="currentColor" stroke="none"/>
</symbol>

<symbol id="ico-tape" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
  <rect x="2.3" y="4.4" width="19.4" height="15.2" rx="1.4"/>
  <circle cx="8.6" cy="12" r="3.1"/>
  <circle cx="15.4" cy="12" r="3.1"/>
  <circle cx="8.6" cy="12" r=".9" fill="currentColor" stroke="none"/>
  <circle cx="15.4" cy="12" r=".9" fill="currentColor" stroke="none"/>
  <path d="M8.6 8.9h6.8"/>
</symbol>

<symbol id="ico-ups" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
  <rect x="1.6" y="6.6" width="17.6" height="10.8" rx="1.6"/>
  <path d="M19.2 10.2h1.4a.8.8 0 0 1 .8.8v2a.8.8 0 0 1-.8.8h-1.4" fill="currentColor" stroke="none"/>
  <path d="M11.4 8.6 8.3 13h2.7l-.9 3.3 3.5-4.6h-2.6z" fill="currentColor" stroke="none"/>
  <path d="M4.3 9.4v5.2"/>
</symbol>

<symbol id="ico-pdu" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
  <rect x="1.4" y="9" width="21.2" height="6" rx="1.4"/>
  <circle cx="5.6" cy="12" r="1.5"/>
  <circle cx="10" cy="12" r="1.5"/>
  <circle cx="14.4" cy="12" r="1.5"/>
  <circle cx="18.8" cy="12" r="1.5"/>
</symbol>

<symbol id="ico-kvm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
  <rect x="2.8" y="4.2" width="18.4" height="11.4" rx="1.4"/>
  <path d="M9.4 19.4h5.2M12 15.6v3.8"/>
  <path d="M8.2 9.9h7.6"/>
  <path d="M13.9 8.1 15.8 9.9 13.9 11.7"/>
  <path d="M10.1 8.1 8.2 9.9 10.1 11.7"/>
</symbol>

<symbol id="ico-console" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
  <rect x="2.4" y="3.4" width="19.2" height="12.2" rx="1.4"/>
  <path d="M8.6 19.6h6.8M12 15.6v4"/>
  <path d="M6 7.2 8.2 9.5 6 11.8"/>
  <path d="M10.6 11.8h6"/>
</symbol>

<symbol id="ico-patch" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round">
  <rect x="1.4" y="7.8" width="21.2" height="8.4" rx="1.2"/>
  <rect x="3.9" y="10.4" width="2.6" height="3.2" rx=".4"/>
  <rect x="8.1" y="10.4" width="2.6" height="3.2" rx=".4"/>
  <rect x="12.3" y="10.4" width="2.6" height="3.2" rx=".4"/>
  <rect x="16.5" y="10.4" width="2.6" height="3.2" rx=".4"/>
  <path d="M20.6 10.4v3.2"/>
</symbol>

<symbol id="ico-blank" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round">
  <rect x="1.6" y="8.6" width="20.8" height="6.8" rx="1.1"/>
  <path d="M5.2 15.4 8.8 8.6M10.2 15.4l3.6-6.8M15.2 15.4l3.6-6.8"/>
</symbol>

<!-- ============ 操作系统 ============ -->

<symbol id="ico-os-windows" viewBox="0 0 24 24" fill="currentColor">
  <path d="M2.6 5.7 10.5 4.5v7.1H2.6z"/>
  <path d="M11.7 4.3 21.4 3v8.6h-9.7z"/>
  <path d="M2.6 12.8h7.9v7.1L2.6 18.6z"/>
  <path d="M11.7 12.8h9.7V21l-9.7-1.3z"/>
</symbol>

<symbol id="ico-os-ubuntu" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
  <circle cx="12" cy="12" r="2.7"/>
  <circle cx="12" cy="3.6" r="2.2" fill="currentColor" stroke="none"/>
  <circle cx="4.7" cy="16.2" r="2.2" fill="currentColor" stroke="none"/>
  <circle cx="19.3" cy="16.2" r="2.2" fill="currentColor" stroke="none"/>
  <path d="M12 9.3V5.9M9.7 13.4 6.6 15.2M14.3 13.4l3.1 1.8" stroke-linecap="round"/>
</symbol>

<symbol id="ico-os-centos" viewBox="0 0 24 24" fill="currentColor">
  <path d="M12 1.8 15.6 5.4 12 9 8.4 5.4z"/>
  <path d="M22.2 12 18.6 15.6 15 12l3.6-3.6z"/>
  <path d="M12 15 15.6 18.6 12 22.2 8.4 18.6z"/>
  <path d="M1.8 12 5.4 8.4 9 12l-3.6 3.6z"/>
</symbol>

<symbol id="ico-os-redhat" viewBox="0 0 24 24" fill="currentColor">
  <path d="M6.3 13.8c-1.4.5-2.3 1.2-2.3 2 0 1.9 3.6 3.4 8 3.4s8-1.5 8-3.4c0-1.1-1.2-2-3.2-2.6l.4 1.3c-1.2.7-3.1 1.2-5.2 1.2-3.1 0-5.7-1-5.7-2.3z"/>
  <path d="M16.4 11.6c.5-1.6-.4-4.3-2-5.4-1.3-.9-2.3-.2-3 .2-.7.4-1.1.2-1.7-.1-.8-.4-1.6.1-1.8 1.3-.2 1 0 2.2.3 3.2.5 1.6 3 2.6 5.3 2.6 1.2 0 2.3-.3 2.9-.8z"/>
</symbol>

<symbol id="ico-os-debian" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round">
  <path d="M16.4 6.4a7.3 7.3 0 1 0 2.2 8"/>
  <path d="M14.6 9.6a3.8 3.8 0 1 0 1.2 4.1"/>
</symbol>

<symbol id="ico-os-linux" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
  <ellipse cx="12" cy="7.2" rx="3.5" ry="4"/>
  <path d="M8.3 11.4c.2 3.9-2.1 5.6-2.1 7.3 0 1.4 2.6 2.5 5.8 2.5s5.8-1.1 5.8-2.5c0-1.7-2.3-3.4-2.1-7.3"/>
  <circle cx="10.6" cy="6.7" r=".75" fill="currentColor" stroke="none"/>
  <circle cx="13.4" cy="6.7" r=".75" fill="currentColor" stroke="none"/>
  <path d="M12 8.4 10.7 9.9h2.6z" fill="currentColor" stroke="none"/>
</symbol>

<symbol id="ico-os-kylin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
  <circle cx="12" cy="12" r="8.8"/>
  <path d="M6.4 16.3c2.1-4.6 6.9-7.4 11-7.6"/>
  <path d="M17.6 7.7c-2.1 4.6-6.9 7.4-11 7.6" opacity=".55"/>
  <circle cx="12" cy="12" r="1.7" fill="currentColor" stroke="none"/>
  <circle cx="17.2" cy="6.9" r=".8" fill="currentColor" stroke="none"/>
</symbol>

<symbol id="ico-os-vmware" viewBox="0 0 24 24" fill="currentColor">
  <rect x="2" y="7" width="2.7" height="10" rx=".7"/>
  <rect x="6.1" y="7" width="2.7" height="6.6" rx=".7"/>
  <rect x="10.2" y="7" width="2.7" height="10" rx=".7"/>
  <rect x="14.3" y="7" width="2.7" height="6.6" rx=".7"/>
  <rect x="18.4" y="7" width="2.7" height="10" rx=".7"/>
</symbol>

<symbol id="ico-os-freebsd" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
  <circle cx="12" cy="13.4" r="6.9"/>
  <path d="M6.8 8.2 4.4 3.6l4.6 2.5"/>
  <path d="M17.2 8.2 19.6 3.6l-4.6 2.5"/>
  <circle cx="9.6" cy="12.4" r=".95" fill="currentColor" stroke="none"/>
  <circle cx="14.4" cy="12.4" r=".95" fill="currentColor" stroke="none"/>
  <path d="M9.2 16.2c1.8 1.5 4.2 1.5 6 0"/>
</symbol>

<symbol id="ico-os-network" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
  <rect x="6.2" y="6.2" width="11.6" height="11.6" rx="2"/>
  <circle cx="12" cy="12" r="2.3"/>
  <path d="M12 6.2V3.2M12 20.8v-3M6.2 12h-3M20.8 12h-3M8.1 8.1 6 6M15.9 8.1 18 6M8.1 15.9 6 18M15.9 15.9 18 18"/>
</symbol>

<symbol id="ico-os-chip" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
  <rect x="6.4" y="6.4" width="11.2" height="11.2" rx="1.6"/>
  <rect x="9.9" y="9.9" width="4.2" height="4.2" rx=".6"/>
  <path d="M9.4 6.4V3.4M14.6 6.4V3.4M9.4 20.6v-3M14.6 20.6v-3M6.4 9.4h-3M6.4 14.6h-3M20.6 9.4h-3M20.6 14.6h-3"/>
</symbol>

<!-- ============ 传感器 ============ -->

<symbol id="ico-sn-temp" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round">
  <path d="M14 13.6V5.4a2 2 0 1 0-4 0v8.2a4 4 0 1 0 4 0z"/>
  <path d="M12 9.6v5.2"/>
</symbol>
<symbol id="ico-sn-humi" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round">
  <path d="M12 3.2c3.8 4.6 5.8 7.8 5.8 10.1a5.8 5.8 0 0 1-11.6 0c0-2.3 2-5.5 5.8-10.1z"/>
</symbol>
<symbol id="ico-sn-smoke" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round">
  <circle cx="12" cy="14.6" r="3.4"/>
  <path d="M5.6 10.2a7.6 7.6 0 0 1 12.8 0"/>
  <path d="M3.4 6.6a11.2 11.2 0 0 1 17.2 0" opacity=".5"/>
</symbol>
<symbol id="ico-sn-water" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round">
  <path d="M2.8 9.6c2 0 2-1.6 4-1.6s2 1.6 4 1.6 2-1.6 4-1.6 2 1.6 4 1.6"/>
  <path d="M2.8 14.4c2 0 2-1.6 4-1.6s2 1.6 4 1.6 2-1.6 4-1.6 2 1.6 4 1.6"/>
  <path d="M2.8 19.2c2 0 2-1.6 4-1.6s2 1.6 4 1.6 2-1.6 4-1.6 2 1.6 4 1.6" opacity=".5"/>
</symbol>
<symbol id="ico-sn-power" viewBox="0 0 24 24" fill="currentColor">
  <path d="M13.4 2.4 5.6 13.2h5.2l-1.4 8.4 8.4-11.4h-5.6z"/>
</symbol>

<!-- ============ 界面 ============ -->

<symbol id="ico-ui-cursor" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round">
  <path d="M5 3.2 18.4 11.6l-5.6 1.4-2.6 5.6z"/>
</symbol>
<symbol id="ico-ui-wall" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round">
  <path d="M3 6.8h18M3 12h18M3 17.2h18"/>
  <path d="M9 3.6v3.2M15 3.6v3.2M6 6.8V12M12 6.8V12M18 6.8V12M9 12v5.2M15 12v5.2"/>
</symbol>
<symbol id="ico-ui-door" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round">
  <path d="M5 20.4V3.6h9.6v16.8z"/>
  <circle cx="12" cy="12" r="1" fill="currentColor" stroke="none"/>
  <path d="M14.6 20.4H20"/>
</symbol>
<symbol id="ico-ui-window" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round">
  <rect x="3.4" y="5" width="17.2" height="14" rx="1.2"/>
  <path d="M12 5v14M3.4 12h17.2"/>
</symbol>
<symbol id="ico-ui-pillar" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
  <circle cx="12" cy="12" r="6.4"/>
  <circle cx="12" cy="12" r="2.4"/>
</symbol>
<symbol id="ico-ui-rack" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
  <rect x="4.4" y="2.6" width="15.2" height="18.8" rx="1.3"/>
  <path d="M7 6.2h10M7 9.6h10M7 13h10M7 16.4h10"/>
</symbol>
<symbol id="ico-ui-sensor" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round">
  <circle cx="12" cy="12" r="2.6"/>
  <path d="M7.4 7.4a6.5 6.5 0 0 0 0 9.2M16.6 16.6a6.5 6.5 0 0 0 0-9.2"/>
  <path d="M4.6 4.6a10.4 10.4 0 0 0 0 14.8M19.4 19.4a10.4 10.4 0 0 0 0-14.8" opacity=".5"/>
</symbol>
<symbol id="ico-ui-trash" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round">
  <path d="M4 6.6h16M9.4 6.6V4.2h5.2v2.4M6.4 6.6l1 13.2h9.2l1-13.2M10.4 10.2v6M13.6 10.2v6"/>
</symbol>
<symbol id="ico-ui-save" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round">
  <path d="M4.6 4.6h11.2L19.4 8.2v11.2H4.6z"/>
  <path d="M8.2 4.6v5h7.2v-5M8.2 19.4v-5.6h7.6v5.6"/>
</symbol>
<symbol id="ico-ui-undo" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round">
  <path d="M4.6 8.4h9.2a5.4 5.4 0 0 1 0 10.8H8.4"/>
  <path d="M8 4.6 4.4 8.4 8 12"/>
</symbol>
<symbol id="ico-ui-redo" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round">
  <path d="M19.4 8.4h-9.2a5.4 5.4 0 0 0 0 10.8h5.4"/>
  <path d="M16 4.6 19.6 8.4 16 12"/>
</symbol>
<symbol id="ico-ui-grid" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
  <rect x="3.4" y="3.4" width="17.2" height="17.2" rx="1.2"/>
  <path d="M9.1 3.4v17.2M14.9 3.4v17.2M3.4 9.1h17.2M3.4 14.9h17.2"/>
</symbol>
<symbol id="ico-ui-fit" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round">
  <path d="M3.6 9V3.6H9M15 3.6h5.4V9M20.4 15v5.4H15M9 20.4H3.6V15"/>
</symbol>
<symbol id="ico-ui-plus" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
  <path d="M12 5v14M5 12h14"/>
</symbol>
<symbol id="ico-ui-edit" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round">
  <path d="M16.4 3.8 20.2 7.6 8.4 19.4l-4.8 1 1-4.8z"/>
</symbol>
<symbol id="ico-ui-search" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round">
  <circle cx="10.6" cy="10.6" r="6.6"/><path d="M15.4 15.4 20.4 20.4"/>
</symbol>
<symbol id="ico-ui-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
  <path d="M6 6 18 18M18 6 6 18"/>
</symbol>
<symbol id="ico-ui-more" viewBox="0 0 24 24" fill="currentColor">
  <circle cx="5.4" cy="12" r="1.9"/><circle cx="12" cy="12" r="1.9"/><circle cx="18.6" cy="12" r="1.9"/>
</symbol>
<symbol id="ico-ui-export" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
  <path d="M12 3.4v11.4M8.2 11l3.8 3.8 3.8-3.8"/><path d="M4.4 16.6v2.2a1.8 1.8 0 0 0 1.8 1.8h11.6a1.8 1.8 0 0 0 1.8-1.8v-2.2"/>
</symbol>
<symbol id="ico-ui-import" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
  <path d="M12 14.8V3.4M8.2 7.2 12 3.4l3.8 3.8"/><path d="M4.4 16.6v2.2a1.8 1.8 0 0 0 1.8 1.8h11.6a1.8 1.8 0 0 0 1.8-1.8v-2.2"/>
</symbol>
<symbol id="ico-ui-cube" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round">
  <path d="M12 2.8 20.6 7.4v9.2L12 21.2 3.4 16.6V7.4z"/>
  <path d="M3.4 7.4 12 12l8.6-4.6M12 12v9.2"/>
</symbol>
<symbol id="ico-ui-plan" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
  <rect x="3.4" y="3.4" width="17.2" height="17.2" rx="1.2"/>
  <path d="M3.4 10h7.2V3.4M10.6 20.6V14h10"/>
</symbol>
<symbol id="ico-ui-alert" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
  <path d="M12 3.6 21.4 20H2.6z"/>
  <path d="M12 9.6v4.4M12 17.2h.01"/>
</symbol>
<symbol id="ico-ui-power" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round">
  <path d="M12 3.4v8"/>
  <path d="M6.8 6.6a7.6 7.6 0 1 0 10.4 0"/>
</symbol>
<symbol id="ico-ui-link" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round">
  <path d="M10 13.6a3.8 3.8 0 0 0 5.6.4l2.8-2.8a3.8 3.8 0 1 0-5.4-5.4l-1.6 1.6"/>
  <path d="M14 10.4a3.8 3.8 0 0 0-5.6-.4l-2.8 2.8a3.8 3.8 0 1 0 5.4 5.4l1.6-1.6"/>
</symbol>
</svg>
