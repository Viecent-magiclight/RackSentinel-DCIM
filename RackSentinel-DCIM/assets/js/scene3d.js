/* =========================================================================
   机房三维场景：墙体挤出 + 机柜阵列 + 逐设备 U 位状态灯
   以 window.Scene3D 暴露，由 dashboard.js 调用
   ========================================================================= */
window.Scene3D = (() => {

const CDN_LIST = [
  'https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js',
  'https://unpkg.com/three@0.160.0/build/three.module.js',
  'https://cdnjs.cloudflare.com/ajax/libs/three.js/0.160.0/three.module.min.js',
  'https://esm.sh/three@0.160.0',
  'https://fastly.jsdelivr.net/npm/three@0.160.0/build/three.module.js',
];

let THREE = null, renderer, scene, cam, raycaster, host, labelHost;
let racksGroup, pickMeshes = [], ledMesh = null, ledIndex = new Map();
let onPick = null, ready = false, rafId = 0, visible = true;
let room = null, rackList = [], deviceList = [];

/* 相机：球坐标轨道 */
const CAM = { tx: 0, ty: 0, tz: 0, dist: 26, theta: -0.9, phi: 0.95, _d: 26, _t: -0.9, _p: 0.95 };
let fly = null;

/* 自动巡航：用户操作后暂停若干秒再恢复 */
const AUTO = { on: true, speed: 0.055, pausedUntil: 0, resumeDelay: 9000 };

/* 动效元素 */
const FX = {
  scan: null,        // 地面扫描环
  scanR: 0,
  scanMax: 1,
  selRing: null,     // 选中机柜的脚下光环
  beams: [],         // 告警机柜光柱
  halos: [],         // 传感器光晕
  caps: [],          // 机柜顶盖（告警时呼吸）
  alertLeds: [],     // 需要闪烁的设备灯实例下标
  ledBase: [],       // 每个设备灯的基准色
};
let tPrev = 0, tNow = 0;

const S = v => v / 100;           // 厘米 → 米

/* ---------------- 初始化 ---------------- */
async function load() {
  for (const url of CDN_LIST) {
    try { const m = await import(url); if (m && m.Scene) { THREE = m; break; } }
    catch { /* 换下一个源 */ }
  }
  return !!THREE;
}

async function init(container, labelContainer, data) {
  host = container; labelHost = labelContainer;
  if (!THREE && !await load()) return false;

  room = data.room; rackList = data.racks; deviceList = data.devices;

  renderer = new THREE.WebGLRenderer({ antialias: true, alpha: false });
  renderer.setPixelRatio(Math.min(devicePixelRatio, 2));
  renderer.shadowMap.enabled = true;
  renderer.shadowMap.type = THREE.PCFSoftShadowMap;
  host.appendChild(renderer.domElement);

  scene = new THREE.Scene();
  scene.background = new THREE.Color(0x040d18);
  scene.fog = new THREE.FogExp2(0x04101c, 0.013);

  cam = new THREE.PerspectiveCamera(42, 1, 0.1, 400);
  raycaster = new THREE.Raycaster();

  buildLights();
  buildFloor();
  buildWalls(data.walls);
  buildRacks();
  buildSensors(data.sensors);
  buildScan();

  CAM.tx = S(room.width) / 2; CAM.tz = S(room.depth) / 2; CAM.ty = 1.2;
  CAM.dist = Math.max(S(room.width), S(room.depth)) * 1.25;
  CAM._d = CAM.dist;

  bindInput();
  resize();
  new ResizeObserver(resize).observe(host);
  ready = true;
  loop();
  return true;
}

function buildLights() {
  scene.add(new THREE.HemisphereLight(0x3a7ca8, 0x071420, 1.05));
  const key = new THREE.DirectionalLight(0xbfe6ff, 1.25);
  key.position.set(14, 26, 10);
  key.castShadow = true;
  key.shadow.mapSize.set(2048, 2048);
  const d = 22;
  Object.assign(key.shadow.camera, { left: -d, right: d, top: d, bottom: -d, near: 1, far: 70 });
  key.shadow.camera.updateProjectionMatrix();
  scene.add(key);
  const rim = new THREE.DirectionalLight(0x2bd7ff, 0.5);
  rim.position.set(-12, 9, -14);
  scene.add(rim);
}

function buildFloor() {
  const W = S(room.width), D = S(room.depth);
  const slab = new THREE.Mesh(
    new THREE.BoxGeometry(W, 0.16, D),
    new THREE.MeshStandardMaterial({ color: 0x0d2537, roughness: .92, metalness: .1, emissive: 0x05151f })
  );
  slab.position.set(W / 2, -0.08, D / 2);
  slab.receiveShadow = true;
  scene.add(slab);

  // 防静电地板分格
  const g = new THREE.Group();
  const mat = new THREE.LineBasicMaterial({ color: 0x1d5f80, transparent: true, opacity: .45 });
  const pts = [];
  for (let x = 0; x <= W + .001; x += 0.6) pts.push(x, 0.005, 0, x, 0.005, D);
  for (let z = 0; z <= D + .001; z += 0.6) pts.push(0, 0.005, z, W, 0.005, z);
  const geo = new THREE.BufferGeometry();
  geo.setAttribute('position', new THREE.Float32BufferAttribute(pts, 3));
  g.add(new THREE.LineSegments(geo, mat));
  scene.add(g);
}

function buildWalls(walls) {
  const H = S(room.wall_height || 300);
  const mats = {
    wall:   new THREE.MeshStandardMaterial({ color: 0x17374c, roughness: .9, transparent: true, opacity: .62 }),
    glass:  new THREE.MeshPhysicalMaterial({ color: 0x4fd4ff, roughness: .1, metalness: 0, transparent: true, opacity: .16, transmission: .6 }),
    door:   new THREE.MeshStandardMaterial({ color: 0xffb020, roughness: .6, transparent: true, opacity: .4, emissive: 0x4a3000 }),
    window: new THREE.MeshStandardMaterial({ color: 0x7dd3fc, roughness: .2, transparent: true, opacity: .3 }),
    pillar: new THREE.MeshStandardMaterial({ color: 0x2b3b4a, roughness: .95 }),
  };
  const litMat = new THREE.MeshBasicMaterial({ color: 0x00e5ff, transparent: true, opacity: .55 });

  walls.forEach(w => {
    const x1 = S(+w.x1), y1 = S(+w.y1), x2 = S(+w.x2), y2 = S(+w.y2), th = S(+w.thickness);
    if (w.kind === 'pillar') {
      const ww = Math.max(Math.abs(x2 - x1), .2), dd = Math.max(Math.abs(y2 - y1), .2);
      const m = new THREE.Mesh(new THREE.BoxGeometry(ww, H, dd), mats.pillar);
      m.position.set((x1 + x2) / 2, H / 2, (y1 + y2) / 2);
      m.castShadow = m.receiveShadow = true;
      scene.add(m);
      return;
    }
    const h    = w.kind === 'door' ? H * .72 : w.kind === 'window' ? H * .45 : H;
    const yOff = w.kind === 'window' ? H * .4 : 0;
    const lit  = w.kind === 'wall' || w.kind === 'glass';
    const mat  = mats[w.kind] || mats.wall;

    const pts = wallPoints(w).map(([px, py]) => [S(px), S(py)]);
    const len = Math.hypot(pts[pts.length - 1][0] - pts[0][0], pts[pts.length - 1][1] - pts[0][1]);

    let body, strip;
    if (pts.length > 2) {
      body  = ribbonMesh(pts, th, h, mat);
      strip = lit ? ribbonMesh(pts, th * 1.15, 0.035, litMat) : null;
    } else {
      if (len < .05) return;
      const rotY = -Math.atan2(pts[1][1] - pts[0][1], pts[1][0] - pts[0][0]);
      const cx = (pts[0][0] + pts[1][0]) / 2, cz = (pts[0][1] + pts[1][1]) / 2;
      body = new THREE.Mesh(new THREE.BoxGeometry(len, h, th), mat);
      body.position.set(cx, h / 2, cz);
      body.rotation.y = rotY;
      if (lit) {
        strip = new THREE.Mesh(new THREE.BoxGeometry(len, 0.035, th * 1.15), litMat);
        strip.position.set(cx, 0, cz);
        strip.rotation.y = rotY;
      }
    }

    body.position.y += yOff;
    body.castShadow = true; body.receiveShadow = true;
    scene.add(body);
    if (strip) {                       // 墙顶灯带
      strip.position.y += h + 0.02;
      scene.add(strip);
    }
  });
}

/**
 * 沿折线挤出一条带厚度的墙。
 *
 * 不能用「一段一根 Box 拼起来」：墙体材质是半透明的，相邻盒子必须互相重叠才不露缝，
 * 而重叠区会被混合两次，相机一转混合顺序就跳变，看上去整面墙在闪。
 * 这里整条弧生成单个闭合截面再挤出，没有自重叠面，也就没有闪烁。
 *
 * @param {number[][]} pts  世界坐标 [x, z] 折线
 */
function ribbonMesh(pts, th, h, mat) {
  const half = th / 2;
  const left = [], right = [];
  for (let i = 0; i < pts.length; i++) {
    // 端点取邻接段方向，中间点取前后两段的平均方向，拐角才不会错位
    const a = pts[Math.max(0, i - 1)], b = pts[Math.min(pts.length - 1, i + 1)];
    const dx = b[0] - a[0], dz = b[1] - a[1];
    const L = Math.hypot(dx, dz) || 1;
    const nx = -dz / L * half, nz = dx / L * half;
    left.push([pts[i][0] + nx, pts[i][1] + nz]);
    right.push([pts[i][0] - nx, pts[i][1] - nz]);
  }

  // Shape 画在 XY 平面、沿 +Z 挤出；rotateX(-90°) 之后 shape.y 会翻成 -worldZ，
  // 所以这里先把 z 取负写进去，转回来正好落在原位
  const shape = new THREE.Shape();
  shape.moveTo(left[0][0], -left[0][1]);
  for (let i = 1; i < left.length; i++) shape.lineTo(left[i][0], -left[i][1]);
  for (let i = right.length - 1; i >= 0; i--) shape.lineTo(right[i][0], -right[i][1]);
  shape.closePath();

  const geo = new THREE.ExtrudeGeometry(shape, { depth: h, bevelEnabled: false });
  geo.rotateX(-Math.PI / 2);
  return new THREE.Mesh(geo, mat);
}

const RACK_H = 2.0;        // 42U 机柜净高（米）
function buildRacks() {
  racksGroup = new THREE.Group();
  scene.add(racksGroup);
  pickMeshes = [];

  const bodyMat = new THREE.MeshStandardMaterial({ color: 0x14202b, roughness: .55, metalness: .55 });

  rackList.forEach(r => {
    const w = S(+r.width), d = S(+r.depth);
    const g = new THREE.Group();
    g.position.set(S(+r.x), 0, S(+r.y));
    g.rotation.y = -(+r.rotation || 0) * Math.PI / 180;

    const body = new THREE.Mesh(new THREE.BoxGeometry(w, RACK_H, d), bodyMat);
    body.position.y = RACK_H / 2;
    body.castShadow = true; body.receiveShadow = true;
    g.add(body);

    const col = new THREE.Color(rackHealth(r));
    const cap = new THREE.Mesh(
      new THREE.BoxGeometry(w * 1.02, 0.05, d * 1.02),
      new THREE.MeshBasicMaterial({ color: col, transparent: true, opacity: .85 })
    );
    cap.position.y = RACK_H + 0.03;
    g.add(cap);

    // 只给有离线设备的机柜立一道光柱，高度控制在层高以内，避免穿出天花板
    const st2 = r.stats || {};
    if (+st2.offline > 0) {
      const h = Math.max(0.5, S(room.wall_height || 300) - RACK_H - 0.25);
      const beam = new THREE.Mesh(
        new THREE.CylinderGeometry(0.1, 0.17, h, 16, 1, true),
        new THREE.MeshBasicMaterial({
          color: 0xff4d6a, transparent: true, opacity: .16,
          side: THREE.DoubleSide, depthWrite: false,
        })
      );
      beam.position.y = RACK_H + 0.1 + h / 2;
      g.add(beam);
      FX.beams.push({ mat: beam.material, phase: Math.random() * 6.28, danger: true });
    }
    FX.caps.push({ mat: cap.material, alert: +st2.offline > 0, phase: Math.random() * 6.28 });

    // 前面板（深色玻璃）
    const face = new THREE.Mesh(
      new THREE.BoxGeometry(w * .94, RACK_H * .96, 0.02),
      new THREE.MeshStandardMaterial({ color: 0x07131d, roughness: .25, metalness: .3,
        transparent: true, opacity: .9 })
    );
    face.position.set(0, RACK_H / 2, -d / 2 - 0.012);
    g.add(face);

    body.userData.rackId = +r.id;
    pickMeshes.push(body);
    racksGroup.add(g);
    r._group = g;
    r._cap = cap;
  });

  buildLeds();
}

/* 每台设备一盏指示灯，使用 InstancedMesh 批量绘制 */
function buildLeds() {
  const valid = deviceList.filter(d => d.type_key !== 'blank');
  if (!valid.length) return;
  const geo = new THREE.BoxGeometry(1, 1, 0.012);
  const mat = new THREE.MeshBasicMaterial({ toneMapped: false });
  ledMesh = new THREE.InstancedMesh(geo, mat, valid.length);
  ledMesh.instanceMatrix.setUsage(THREE.DynamicDrawUsage);
  ledMesh.frustumCulled = false;
  scene.add(ledMesh);

  const rackMap = new Map(rackList.map(r => [+r.id, r]));
  const world = new THREE.Matrix4(), local = new THREE.Matrix4(), out = new THREE.Matrix4();
  const pos = new THREE.Vector3(), scl = new THREE.Vector3();
  const quat = new THREE.Quaternion();
  const color = new THREE.Color();
  const hidden = new THREE.Matrix4().makeScale(0, 0, 0);
  ledIndex.clear();

  valid.forEach((d, i) => {
    const r = rackMap.get(+d.rack_id);
    if (!r) { ledMesh.setMatrixAt(i, hidden); return; }
    const w = S(+r.width), dp = S(+r.depth), uTot = +r.u_total || 42;
    const uh = RACK_H * .96 / uTot;
    const cy = (RACK_H * .02) + (+d.u_start - 1 + (+d.u_size) / 2) * uh;
    const h  = Math.max(uh * +d.u_size - 0.006, 0.008);

    world.makeRotationY(-(+r.rotation || 0) * Math.PI / 180);
    world.setPosition(S(+r.x), 0, S(+r.y));
    pos.set(0, cy, -dp / 2 - 0.022);
    scl.set(w * .86, h, 1);
    local.compose(pos, quat, scl);
    out.multiplyMatrices(world, local);

    ledMesh.setMatrixAt(i, out);
    color.set(statusColor(d.status));
    ledMesh.setColorAt(i, color);
    ledIndex.set(+d.id, i);
    FX.ledBase[i] = color.clone();
    if (d.status !== 'online') FX.alertLeds.push(i);
  });
  ledMesh.instanceMatrix.needsUpdate = true;
  if (ledMesh.instanceColor) ledMesh.instanceColor.needsUpdate = true;
}

/** 选中机柜：在它脚下放一圈脉动光环，让浮层和场景对得上号 */
function setSelected(rackId) {
  if (!FX.selRing) {
    FX.selRing = new THREE.Mesh(
      new THREE.RingGeometry(0.52, 0.66, 48),
      new THREE.MeshBasicMaterial({ color: 0x00e5ff, transparent: true,
        opacity: .9, side: THREE.DoubleSide, depthWrite: false })
    );
    FX.selRing.rotation.x = -Math.PI / 2;
    scene.add(FX.selRing);
  }
  const r = rackList.find(x => +x.id === +rackId);
  FX.selRing.visible = !!r;
  if (r) FX.selRing.position.set(S(+r.x), 0.05, S(+r.y));
}

/** 地面雷达扫描环，由机房中心向外扩散 */
function buildScan() {
  const W = S(room.width), D = S(room.depth);
  FX.scan = new THREE.Mesh(
    new THREE.RingGeometry(0.955, 1, 96),
    new THREE.MeshBasicMaterial({
      color: 0x00e5ff, transparent: true, opacity: .5,
      side: THREE.DoubleSide, depthWrite: false,
    })
  );
  FX.scan.rotation.x = -Math.PI / 2;
  FX.scan.position.set(W / 2, 0.03, D / 2);
  FX.scanMax = Math.hypot(W, D) / 2;
  scene.add(FX.scan);
}

function buildSensors(sensors) {
  (sensors || []).forEach(s => {
    const col = s.status === 'alarm' ? 0xff4d6a : s.status === 'warning' ? 0xffb020 : 0x2ef2a3;
    const m = new THREE.Mesh(
      new THREE.SphereGeometry(0.1, 14, 10),
      new THREE.MeshBasicMaterial({ color: col })
    );
    m.position.set(S(+s.x), S(room.wall_height || 300) * .92, S(+s.y));
    scene.add(m);
    const halo = new THREE.Mesh(
      new THREE.RingGeometry(0.16, 0.24, 24),
      new THREE.MeshBasicMaterial({ color: col, transparent: true, opacity: .4, side: THREE.DoubleSide })
    );
    halo.rotation.x = -Math.PI / 2;
    halo.position.copy(m.position);
    scene.add(halo);
    FX.halos.push({ mesh: halo, mat: halo.material, phase: Math.random() * 6.28 });
  });
}

/* ---------------- 交互 ---------------- */
function bindInput() {
  const el = renderer.domElement;
  let down = false, lx = 0, ly = 0, moved = 0, btn = 0;

  el.addEventListener('mousedown', e => {
    down = true; btn = e.button; lx = e.clientX; ly = e.clientY; moved = 0;
    pauseAuto();
  });
  window.addEventListener('mouseup', e => {
    if (down && moved < 5 && btn === 0) pick(e);
    down = false;
  });
  window.addEventListener('mousemove', e => {
    if (!down) return;
    const dx = e.clientX - lx, dy = e.clientY - ly;
    lx = e.clientX; ly = e.clientY; moved += Math.abs(dx) + Math.abs(dy);
    if (btn === 2 || e.shiftKey) {
      const k = CAM.dist * 0.0014;
      const sn = Math.sin(CAM.theta), cs = Math.cos(CAM.theta);
      CAM.tx -= (dx * cs - dy * sn) * k;
      CAM.tz -= (dx * sn + dy * cs) * k;
    } else {
      CAM.theta -= dx * 0.006;
      CAM.phi = Math.max(0.12, Math.min(1.5, CAM.phi - dy * 0.005));
    }
    fly = null;
  });
  el.addEventListener('wheel', e => {
    e.preventDefault();
    CAM.dist = Math.max(3, Math.min(90, CAM.dist * (e.deltaY > 0 ? 1.1 : 1 / 1.1)));
    fly = null;
    pauseAuto();
  }, { passive: false });
  el.addEventListener('contextmenu', e => e.preventDefault());
}

function pick(e) {
  const r = renderer.domElement.getBoundingClientRect();
  const nd = new THREE.Vector2(
    ((e.clientX - r.left) / r.width) * 2 - 1,
    -((e.clientY - r.top) / r.height) * 2 + 1
  );
  raycaster.setFromCamera(nd, cam);
  const hit = raycaster.intersectObjects(pickMeshes, false)[0];
  if (onPick) onPick(hit ? hit.object.userData.rackId : null);
  if (hit) {
    const rk = rackList.find(x => +x.id === hit.object.userData.rackId);
    if (rk) flyTo(S(+rk.x), 1.1, S(+rk.y), 6.5);
  }
}

function pauseAuto() {
  AUTO.pausedUntil = performance.now() + AUTO.resumeDelay;
}

function flyTo(x, y, z, dist) {
  fly = { t: 0, fx: CAM.tx, fy: CAM.ty, fz: CAM.tz, fd: CAM.dist, tx: x, ty: y, tz: z, td: dist };
}
function reset() {
  flyTo(S(room.width) / 2, 1.2, S(room.depth) / 2, Math.max(S(room.width), S(room.depth)) * 1.25);
  AUTO.pausedUntil = 0;   // 复位视角后立刻恢复巡航
}

/* ---------------- 渲染循环 ---------------- */
function resize() {
  if (!renderer) return;
  const r = host.getBoundingClientRect();
  if (r.width < 2 || r.height < 2) return;
  renderer.setSize(r.width, r.height, false);
  cam.aspect = r.width / r.height;
  cam.updateProjectionMatrix();
}

function loop() {
  rafId = requestAnimationFrame(loop);
  tNow = performance.now();
  const dt = Math.min(0.08, (tNow - tPrev) / 1000) || 0.016;
  tPrev = tNow;
  if (!visible) return;

  // 自动巡航：无 flyTo 动画且过了交互冷却期才转
  if (AUTO.on && !fly && tNow > AUTO.pausedUntil) {
    CAM.theta += dt * AUTO.speed;
  }
  stepFx(dt);

  if (fly) {
    fly.t = Math.min(1, fly.t + 0.045);
    const k = 1 - Math.pow(1 - fly.t, 3);
    CAM.tx = fly.fx + (fly.tx - fly.fx) * k;
    CAM.ty = fly.fy + (fly.ty - fly.fy) * k;
    CAM.tz = fly.fz + (fly.tz - fly.fz) * k;
    CAM.dist = fly.fd + (fly.td - fly.fd) * k;
    if (fly.t >= 1) fly = null;
  }
  CAM._d += (CAM.dist - CAM._d) * .14;
  CAM._t += (CAM.theta - CAM._t) * .18;
  CAM._p += (CAM.phi - CAM._p) * .18;

  const r = CAM._d;
  cam.position.set(
    CAM.tx + r * Math.cos(CAM._p) * Math.sin(CAM._t),
    CAM.ty + r * Math.sin(CAM._p),
    CAM.tz + r * Math.cos(CAM._p) * Math.cos(CAM._t)
  );
  cam.lookAt(CAM.tx, CAM.ty, CAM.tz);
  renderer.render(scene, cam);
  drawLabels();
}

/* 逐帧动效：扫描环扩散、告警光柱与顶盖呼吸、传感器光晕、告警设备灯闪烁 */
function stepFx(dt) {
  const t = tNow / 1000;

  if (FX.scan) {
    FX.scanR += dt * FX.scanMax * 0.22;
    if (FX.scanR > FX.scanMax) FX.scanR = 0.2;
    const k = FX.scanR / FX.scanMax;
    FX.scan.scale.setScalar(Math.max(0.01, FX.scanR));
    FX.scan.material.opacity = 0.75 * (1 - k);
  }

  FX.beams.forEach(b => {
    b.mat.opacity = 0.07 + 0.16 * (0.5 + 0.5 * Math.sin(t * 3.0 + b.phase));
  });
  FX.caps.forEach(c => {
    c.mat.opacity = c.alert ? 0.55 + 0.4 * (0.5 + 0.5 * Math.sin(t * 3.2 + c.phase)) : 0.85;
  });
  if (FX.selRing && FX.selRing.visible) {
    const s = 1 + 0.12 * Math.sin(t * 4.2);
    FX.selRing.scale.set(s, s, 1);
    FX.selRing.material.opacity = 0.6 + 0.35 * (0.5 + 0.5 * Math.sin(t * 4.2));
  }
  FX.halos.forEach(h => {
    const s = 1 + 0.3 * (0.5 + 0.5 * Math.sin(t * 1.9 + h.phase));
    h.mesh.scale.setScalar(s);
    h.mat.opacity = 0.45 - 0.22 * (s - 1) / 0.3;
  });

  if (ledMesh && FX.alertLeds.length) {
    const p = 0.35 + 0.65 * (0.5 + 0.5 * Math.sin(t * 5.0));
    const c = new THREE.Color();
    FX.alertLeds.forEach(i => {
      const base = FX.ledBase[i];
      if (!base) return;
      ledMesh.setColorAt(i, c.copy(base).multiplyScalar(p));
    });
    if (ledMesh.instanceColor) ledMesh.instanceColor.needsUpdate = true;
  }
}

/* 机柜名称 HTML 标签 */
let labelNodes = null, _lv = null;
function drawLabels() {
  if (!labelHost) return;
  if (!_lv) _lv = new THREE.Vector3();
  if (!labelNodes) {
    labelNodes = rackList.map(r => {
      const n = document.createElement('div');
      n.style.cssText = 'position:absolute;left:0;top:0;font-size:11px;color:#bff0ff;' +
        'background:rgba(4,20,34,.78);border:1px solid rgba(0,229,255,.35);padding:1px 6px;' +
        'white-space:nowrap;will-change:transform;font-family:Bahnschrift,sans-serif';
      n.textContent = r.name;
      labelHost.appendChild(n);
      return n;
    });
  }
  const rect = host.getBoundingClientRect();
  rackList.forEach((r, i) => {
    const p = _lv.set(S(+r.x), RACK_H + 0.22, S(+r.y)).project(cam);
    const n = labelNodes[i];
    if (p.z > 1 || Math.abs(p.x) > 1.2 || Math.abs(p.y) > 1.2) { n.style.display = 'none'; return; }
    n.style.display = '';
    n.style.transform = `translate3d(${(p.x * .5 + .5) * rect.width}px,${(-p.y * .5 + .5) * rect.height}px,0) translate(-50%,-100%)`;
  });
}

/* ---------------- 对外接口 ---------------- */
function updateMetrics(metrics) {
  if (!ledMesh || !metrics) return;
  const alert = new Set(FX.alertLeds);
  const color = new THREE.Color();
  metrics.forEach(m => {
    const i = ledIndex.get(+m.id);
    if (i === undefined) return;
    const base = m.cpu > 85 ? '#ff4d6a' : m.cpu > 65 ? '#ffb020' : '#2ef2a3';
    color.set(base);
    const k = 0.55 + 0.45 * Math.min(1, m.cpu / 80);
    color.multiplyScalar(m.cpu > 0 ? k : 0.18);
    FX.ledBase[i] = color.clone();
    // 告警设备的实际颜色由 stepFx 的闪烁逻辑负责，这里只更新基准色
    if (!alert.has(i)) ledMesh.setColorAt(i, color);
  });
  if (ledMesh.instanceColor) ledMesh.instanceColor.needsUpdate = true;
}

function setVisible(v) {
  visible = v;
  if (renderer) renderer.domElement.style.display = v ? '' : 'none';
  if (labelHost) labelHost.style.display = v ? '' : 'none';
  if (v) resize();
}

function setAuto(on) {
  AUTO.on = on;
  if (on) AUTO.pausedUntil = 0;
}

return {
  init, reset, updateMetrics, setVisible, setAuto, setSelected,
  isAuto: () => AUTO.on,
  onPick: cb => { onPick = cb; },
  isReady: () => ready,
  stop: () => cancelAnimationFrame(rafId),
};
})();
