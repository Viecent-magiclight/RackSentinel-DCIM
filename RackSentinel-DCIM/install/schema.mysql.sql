-- ===========================================================
--  机房机柜资产与运维管理平台 —— MySQL 表结构
-- ===========================================================
SET NAMES utf8mb4;

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

-- 登录账号
CREATE TABLE users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(40)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  display_name  VARCHAR(60)  NOT NULL DEFAULT '',
  role          VARCHAR(16)  NOT NULL DEFAULT 'admin',
  last_login_at DATETIME     DEFAULT NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 站点配置：键值对，页面标题之类可在设置页里改
CREATE TABLE settings (
  k VARCHAR(64) PRIMARY KEY,
  v TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 登录失败记录，按来源 IP 限速
CREATE TABLE login_attempts (
  id       INT AUTO_INCREMENT PRIMARY KEY,
  ip       VARCHAR(45) NOT NULL,
  username VARCHAR(40) NOT NULL DEFAULT '',
  at       DATETIME    NOT NULL,
  INDEX idx_attempt (ip, at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 机房
CREATE TABLE rooms (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  code        VARCHAR(40)  NOT NULL UNIQUE COMMENT '机房编码',
  name        VARCHAR(80)  NOT NULL COMMENT '机房名称',
  width       INT          NOT NULL DEFAULT 1200 COMMENT '平面宽度(cm)',
  depth       INT          NOT NULL DEFAULT 900  COMMENT '平面进深(cm)',
  grid        INT          NOT NULL DEFAULT 10   COMMENT '吸附网格(cm)',
  wall_height INT          NOT NULL DEFAULT 300  COMMENT '层高(cm)',
  note        VARCHAR(255) DEFAULT NULL,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 平面图图元：墙体 / 玻璃隔断 / 门 / 窗 / 柱
CREATE TABLE walls (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  room_id    INT         NOT NULL,
  kind       VARCHAR(16) NOT NULL DEFAULT 'wall',
  x1         INT NOT NULL, y1 INT NOT NULL,
  x2         INT NOT NULL, y2 INT NOT NULL,
  thickness  INT NOT NULL DEFAULT 24,
  curve      INT NOT NULL DEFAULT 0 COMMENT '弧高(cm)，0 为直墙，正负决定凸向',
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_walls_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
  INDEX idx_walls_room (room_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 机柜
CREATE TABLE racks (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  room_id        INT         NOT NULL,
  name           VARCHAR(64) NOT NULL COMMENT '机柜编号 如 A01',
  label          VARCHAR(80) DEFAULT NULL COMMENT '用途标签',
  row_label      VARCHAR(16) DEFAULT NULL COMMENT '列/排',
  x              INT NOT NULL DEFAULT 0 COMMENT '平面中心 X(cm)',
  y              INT NOT NULL DEFAULT 0 COMMENT '平面中心 Y(cm)',
  width          INT NOT NULL DEFAULT 60  COMMENT '宽(cm)',
  depth          INT NOT NULL DEFAULT 110 COMMENT '深(cm)',
  rotation       INT NOT NULL DEFAULT 0   COMMENT '0/90/180/270',
  u_total        INT NOT NULL DEFAULT 42,
  power_capacity INT NOT NULL DEFAULT 6000 COMMENT '额定功率(W)',
  note           VARCHAR(255) DEFAULT NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_racks_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
  INDEX idx_racks_room (room_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 机柜内设备（按 U 位安装）
CREATE TABLE devices (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  rack_id    INT          NOT NULL,
  name       VARCHAR(100) NOT NULL COMMENT '设备名称',
  hostname   VARCHAR(100) DEFAULT NULL COMMENT '主机名',
  ip         VARCHAR(45)  DEFAULT NULL COMMENT '业务 IP',
  mgmt_ip    VARCHAR(45)  DEFAULT NULL COMMENT '带外管理 IP',
  type_key   VARCHAR(32)  NOT NULL DEFAULT 'server',
  os_key     VARCHAR(32)  DEFAULT 'none',
  u_start    INT          NOT NULL COMMENT '起始 U（1 = 柜底）',
  u_size     INT          NOT NULL DEFAULT 1,
  vendor     VARCHAR(60)  DEFAULT NULL,
  model      VARCHAR(80)  DEFAULT NULL,
  serial     VARCHAR(80)  DEFAULT NULL,
  asset_tag  VARCHAR(60)  DEFAULT NULL,
  owner      VARCHAR(60)  DEFAULT NULL COMMENT '责任人/业务归属',
  status     VARCHAR(16)  NOT NULL DEFAULT 'online',
  power_w    INT          NOT NULL DEFAULT 0,
  note       VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_devices_rack FOREIGN KEY (rack_id) REFERENCES racks(id) ON DELETE CASCADE,
  INDEX idx_devices_rack (rack_id),
  INDEX idx_devices_type (type_key),
  INDEX idx_devices_status (status),
  INDEX idx_devices_ip (ip)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 设备实时/历史指标
CREATE TABLE device_metrics (
  id          BIGINT AUTO_INCREMENT PRIMARY KEY,
  device_id   INT NOT NULL,
  cpu         DECIMAL(5,2) DEFAULT 0,
  mem         DECIMAL(5,2) DEFAULT 0,
  disk        DECIMAL(5,2) DEFAULT 0,
  temp        DECIMAL(5,2) DEFAULT 0,
  net_in      DECIMAL(10,2) DEFAULT 0 COMMENT 'Mbps',
  net_out     DECIMAL(10,2) DEFAULT 0 COMMENT 'Mbps',
  power       DECIMAL(8,2) DEFAULT 0  COMMENT 'W',
  recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_metrics_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
  INDEX idx_metrics_device_time (device_id, recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 告警
CREATE TABLE alerts (
  id         BIGINT AUTO_INCREMENT PRIMARY KEY,
  device_id  INT DEFAULT NULL,
  rack_id    INT DEFAULT NULL,
  level      VARCHAR(16) NOT NULL DEFAULT 'warning' COMMENT 'critical/warning/info',
  title      VARCHAR(160) NOT NULL,
  detail     VARCHAR(255) DEFAULT NULL,
  acked      TINYINT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_alerts_time (created_at),
  INDEX idx_alerts_level (level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 机房环境传感器
CREATE TABLE sensors (
  id      INT AUTO_INCREMENT PRIMARY KEY,
  room_id INT NOT NULL,
  name    VARCHAR(60) NOT NULL,
  kind    VARCHAR(24) NOT NULL DEFAULT 'temp',
  x       INT NOT NULL DEFAULT 0,
  y       INT NOT NULL DEFAULT 0,
  value   DECIMAL(8,2) DEFAULT 0,
  unit    VARCHAR(12) DEFAULT '',
  status  VARCHAR(16) DEFAULT 'normal',
  CONSTRAINT fk_sensors_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
  INDEX idx_sensors_room (room_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
