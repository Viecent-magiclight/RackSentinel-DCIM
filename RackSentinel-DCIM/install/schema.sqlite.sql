-- ===========================================================
--  机房机柜资产与运维管理平台 —— SQLite 表结构（与 MySQL 版本字段一致）
-- ===========================================================
DROP TABLE IF EXISTS device_metrics;
DROP TABLE IF EXISTS alerts;
DROP TABLE IF EXISTS devices;
DROP TABLE IF EXISTS racks;
DROP TABLE IF EXISTS sensors;
DROP TABLE IF EXISTS walls;
DROP TABLE IF EXISTS rooms;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  username      TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  display_name  TEXT NOT NULL DEFAULT '',
  role          TEXT NOT NULL DEFAULT 'admin',
  last_login_at TEXT,
  created_at    TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

CREATE TABLE settings (
  k TEXT PRIMARY KEY,
  v TEXT
);

CREATE TABLE login_attempts (
  id       INTEGER PRIMARY KEY AUTOINCREMENT,
  ip       TEXT NOT NULL,
  username TEXT NOT NULL DEFAULT '',
  at       TEXT NOT NULL
);

CREATE TABLE rooms (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  code        TEXT    NOT NULL UNIQUE,
  name        TEXT    NOT NULL,
  width       INTEGER NOT NULL DEFAULT 1200,
  depth       INTEGER NOT NULL DEFAULT 900,
  grid        INTEGER NOT NULL DEFAULT 10,
  wall_height INTEGER NOT NULL DEFAULT 300,
  note        TEXT,
  created_at  TEXT    NOT NULL DEFAULT (datetime('now','localtime'))
);

CREATE TABLE walls (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  room_id    INTEGER NOT NULL REFERENCES rooms(id) ON DELETE CASCADE,
  kind       TEXT    NOT NULL DEFAULT 'wall',
  x1 INTEGER NOT NULL, y1 INTEGER NOT NULL,
  x2 INTEGER NOT NULL, y2 INTEGER NOT NULL,
  thickness  INTEGER NOT NULL DEFAULT 24,
  curve      INTEGER NOT NULL DEFAULT 0,   -- 弧高(cm)，0 为直墙，正负决定凸向
  sort_order INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX idx_walls_room ON walls(room_id);

CREATE TABLE racks (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  room_id        INTEGER NOT NULL REFERENCES rooms(id) ON DELETE CASCADE,
  name           TEXT    NOT NULL,
  label          TEXT,
  row_label      TEXT,
  x              INTEGER NOT NULL DEFAULT 0,
  y              INTEGER NOT NULL DEFAULT 0,
  width          INTEGER NOT NULL DEFAULT 60,
  depth          INTEGER NOT NULL DEFAULT 110,
  rotation       INTEGER NOT NULL DEFAULT 0,
  u_total        INTEGER NOT NULL DEFAULT 42,
  power_capacity INTEGER NOT NULL DEFAULT 6000,
  note           TEXT,
  created_at     TEXT    NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE INDEX idx_racks_room ON racks(room_id);

CREATE TABLE devices (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  rack_id    INTEGER NOT NULL REFERENCES racks(id) ON DELETE CASCADE,
  name       TEXT    NOT NULL,
  hostname   TEXT,
  ip         TEXT,
  mgmt_ip    TEXT,
  type_key   TEXT    NOT NULL DEFAULT 'server',
  os_key     TEXT    DEFAULT 'none',
  u_start    INTEGER NOT NULL,
  u_size     INTEGER NOT NULL DEFAULT 1,
  vendor     TEXT,
  model      TEXT,
  serial     TEXT,
  asset_tag  TEXT,
  owner      TEXT,
  status     TEXT    NOT NULL DEFAULT 'online',
  power_w    INTEGER NOT NULL DEFAULT 0,
  note       TEXT,
  created_at TEXT    NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE INDEX idx_devices_rack ON devices(rack_id);
CREATE INDEX idx_devices_type ON devices(type_key);
CREATE INDEX idx_devices_status ON devices(status);
CREATE INDEX idx_devices_ip ON devices(ip);

CREATE TABLE device_metrics (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  device_id   INTEGER NOT NULL REFERENCES devices(id) ON DELETE CASCADE,
  cpu         REAL DEFAULT 0,
  mem         REAL DEFAULT 0,
  disk        REAL DEFAULT 0,
  temp        REAL DEFAULT 0,
  net_in      REAL DEFAULT 0,
  net_out     REAL DEFAULT 0,
  power       REAL DEFAULT 0,
  recorded_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE INDEX idx_metrics_device_time ON device_metrics(device_id, recorded_at);

CREATE TABLE alerts (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  device_id  INTEGER,
  rack_id    INTEGER,
  level      TEXT NOT NULL DEFAULT 'warning',
  title      TEXT NOT NULL,
  detail     TEXT,
  acked      INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE INDEX idx_alerts_time ON alerts(created_at);

CREATE TABLE sensors (
  id      INTEGER PRIMARY KEY AUTOINCREMENT,
  room_id INTEGER NOT NULL REFERENCES rooms(id) ON DELETE CASCADE,
  name    TEXT NOT NULL,
  kind    TEXT NOT NULL DEFAULT 'temp',
  x       INTEGER NOT NULL DEFAULT 0,
  y       INTEGER NOT NULL DEFAULT 0,
  value   REAL DEFAULT 0,
  unit    TEXT DEFAULT '',
  status  TEXT DEFAULT 'normal'
);
CREATE INDEX idx_sensors_room ON sensors(room_id);
