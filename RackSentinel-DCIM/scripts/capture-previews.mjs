#!/usr/bin/env node
/**
 * Capture README Preview screenshots at 1920x1080 (requires logged-in session).
 * Usage: node scripts/capture-previews.mjs [baseUrl] [outDir]
 */
import puppeteer from 'puppeteer';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');
const repoRoot = path.resolve(root, '..');

const BASE = (process.argv[2] || 'http://127.0.0.1:8123').replace(/\/$/, '');
const OUT = path.resolve(process.argv[3] || path.join(repoRoot, 'Preview'));
const VIEW = { width: 1920, height: 1080, deviceScaleFactor: 1 };

const deviceId = fs.existsSync(path.join(root, 'storage/.screenshot-device-id'))
  ? fs.readFileSync(path.join(root, 'storage/.screenshot-device-id'), 'utf8').trim()
  : '1';

async function login(page) {
  await page.goto(`${BASE}/login.php`, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.type('input[name="username"]', 'admin', { delay: 15 });
  await page.type('input[name="password"]', 'admin123', { delay: 15 });
  await page.click('button[type="submit"]');
  await page.waitForFunction(
    () => !location.pathname.endsWith('login.php'),
    { timeout: 60000 },
  );
}

async function shot(page, name, url, opts = {}) {
  const { waitMs = 2500, before } = opts;
  await page.goto(`${BASE}${url}`, { waitUntil: 'domcontentloaded', timeout: 120000 });
  if (before) await before(page);
  if (waitMs) await new Promise((r) => setTimeout(r, waitMs));
  const file = path.join(OUT, name);
  await page.screenshot({ path: file, type: 'png' });
  console.log('wrote', file);
}

async function main() {
  fs.mkdirSync(OUT, { recursive: true });
  const browser = await puppeteer.launch({
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox', `--window-size=${VIEW.width},${VIEW.height}`],
    defaultViewport: VIEW,
  });
  const page = await browser.newPage();
  await page.setViewport(VIEW);

  await login(page);

  await shot(page, 'Dashboard1.png', '/index.php', {
    waitMs: 6000,
    before: async (p) => {
      await p.waitForSelector('#tab3d', { timeout: 30000 }).catch(() => {});
      await p.click('#tab3d').catch(() => {});
    },
  });

  await shot(page, 'Dashboard2.png', '/index.php', {
    waitMs: 3000,
    before: async (p) => {
      await p.waitForSelector('#tab2d', { timeout: 30000 });
      await p.click('#tab2d');
    },
  });

  await shot(page, 'Floor Plan Design.png', '/planner.php', { waitMs: 4000 });

  await shot(page, 'Rack Layout Editor.png', '/racks.php', {
    waitMs: 3500,
    before: async (p) => {
      await p.waitForSelector('#rackList .rackitem', { timeout: 30000 });
      await p.click('#rackList .rackitem');
    },
  });

  await shot(page, 'Asset Management.png', '/devices.php', { waitMs: 3000 });

  await shot(page, 'Settings.png', '/settings.php', { waitMs: 2000 });

  await shot(page, 'Monitor Ingest.png', '/monitor.php', { waitMs: 3000 });

  await shot(page, 'Alerts Center.png', '/alerts.php', { waitMs: 2500 });

  await shot(page, 'Device Monitor.png', `/device-monitor.php?id=${deviceId}`, {
    waitMs: 1500,
    before: async (p) => {
      await p.waitForFunction(
        () => {
          const el = document.querySelector('#summary');
          return el && !el.textContent.includes('加载中');
        },
        { timeout: 30000 },
      );
    },
  });

  await browser.close();
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
