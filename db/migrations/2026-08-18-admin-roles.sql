-- Роли администраторов: приводим ENUM к тому набору, который знает код
-- (admin/includes/security_config.php $ALLOWED_ROLES и admin/includes/permissions.php).
-- До миграции колонка была enum('admin','manager'), поэтому роли superadmin и viewer
-- были недостижимы, а редактирование суперадмина через форму молча понижало его.
--
-- Безопасно для существующих данных: старые значения 'admin' и 'manager' входят
-- в новый набор и остаются на месте, DEFAULT не меняется.
ALTER TABLE `admins`
  MODIFY COLUMN `role`
  ENUM('superadmin','admin','manager','viewer')
  COLLATE utf8mb4_unicode_ci
  NOT NULL DEFAULT 'manager';

-- Управление учётными записями (создание/правка/удаление) в новой модели прав
-- требует edit_users/delete_users, а они есть только у superadmin.
-- Чтобы система не осталась без владельца, поднимаем старейшего активного
-- администратора до superadmin — но только если суперадмина ещё нет.
UPDATE `admins`
SET `role` = 'superadmin'
WHERE `role` = 'admin'
  AND `is_active` = 1
  AND NOT EXISTS (SELECT 1 FROM (SELECT * FROM `admins`) AS a WHERE a.`role` = 'superadmin')
ORDER BY `id` ASC
LIMIT 1;
