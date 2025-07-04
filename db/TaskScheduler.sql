-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1:3306
-- Время создания: Июн 24 2025 г., 17:03
-- Версия сервера: 10.8.4-MariaDB
-- Версия PHP: 8.1.9

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `TaskScheduler`
--

-- --------------------------------------------------------

--
-- Структура таблицы `events`
--

CREATE TABLE `events` (
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `task_id` int(11) DEFAULT NULL,
  `title` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime NOT NULL,
  `event_type` enum('meeting','deadline','reminder','birthday','other') COLLATE utf8mb4_unicode_ci DEFAULT 'other',
  `color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT '#5e35b1',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `related_entity_type` enum('task','event','reminder') COLLATE utf8mb4_unicode_ci NOT NULL,
  `related_entity_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `projects`
--

CREATE TABLE `projects` (
  `project_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT '#5e35b1',
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'fa-briefcase',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `projects`
--

INSERT INTO `projects` (`project_id`, `user_id`, `name`, `description`, `color`, `icon`, `created_at`, `updated_at`) VALUES
(1, 1, 'Работа', 'Профессиональные задачи', '#5e35b1', 'fa-briefcase', '2025-05-28 09:33:24', '2025-05-28 09:33:24'),
(2, 1, 'Обучение', 'Задачи по самообразованию', '#26a69a', 'fa-graduation-cap', '2025-05-28 09:33:24', '2025-05-28 09:33:24'),
(3, 1, 'Личное', 'Персональные дела', '#ff7043', 'fa-heart', '2025-05-28 09:33:24', '2025-05-28 09:33:24');

-- --------------------------------------------------------

--
-- Структура таблицы `reminders`
--

CREATE TABLE `reminders` (
  `reminder_id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `remind_at` datetime NOT NULL,
  `is_sent` tinyint(1) DEFAULT 0,
  `sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `subtasks`
--

CREATE TABLE `subtasks` (
  `subtask_id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `title` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_completed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `tasks`
--

CREATE TABLE `tasks` (
  `task_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `project_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `title` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  `priority` enum('low','medium','high') COLLATE utf8mb4_unicode_ci DEFAULT 'medium',
  `is_completed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `tasks`
--

INSERT INTO `tasks` (`task_id`, `user_id`, `project_id`, `category_id`, `title`, `description`, `due_date`, `priority`, `is_completed`, `created_at`, `updated_at`, `completed_at`) VALUES
(1, 1, 1, 3, 'Сделать уроки', 'после тяжелого учебного дня надо поесть помыться и сесть учить стихи маяковского', '2025-05-07 16:38:57', 'high', 0, '2025-05-13 13:38:57', '2025-06-24 06:16:23', '2025-05-23 16:38:57'),
(2, 1, 2, 2, 'прочитать главу по истории ', 'прочитать и законспектировать основные моменты из главы про древний рим. обратить внимание на даты и имена ключевых фигур', '2025-06-27 08:47:53', 'low', 1, '2025-06-24 03:48:48', '2025-06-24 06:16:58', NULL),
(3, 1, 1, 2, 'подготовить доклад по биологии', 'собрать информацию о процессе фотосинтеза и подготовить короткий доклад для выступления на уроке. не забудь картинки!', '2025-06-28 09:03:07', 'high', 1, '2025-06-24 04:03:44', '2025-06-24 06:16:40', NULL),
(4, 1, 2, 2, 'убраться в комнате\r\n\r\n', 'навести порядок на столе и полках, сложить вещи в шкаф. главное - выкинуть накопившийся мусор!', '2025-06-23 09:03:07', 'medium', 0, '2025-06-24 04:03:44', '2025-06-24 06:14:57', NULL),
(5, 1, 1, 2, 'позвонить бабушке/дедушке \r\n', 'позвонить бабушке или дедушке, узнать как у них дела и рассказать о своих новостях. им будет приятно', '2025-06-28 09:03:07', 'medium', 0, '2025-06-24 04:03:46', '2025-06-24 06:15:34', NULL),
(6, 1, 2, 2, 'пойти на тренировку', 'не пропустить тренировку! разомнись хорошо и выложись на все 100%', '2025-06-23 09:03:07', 'medium', 0, '2025-06-24 04:03:46', '2025-06-24 06:16:04', NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `task_categories`
--

CREATE TABLE `task_categories` (
  `category_id` int(11) NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `task_categories`
--

INSERT INTO `task_categories` (`category_id`, `name`, `description`) VALUES
(1, 'Работа', 'Задачи, связанные с профессиональной деятельностью'),
(2, 'Обучение', 'Задачи по самообразованию и обучению'),
(3, 'Личное', 'Персональные задачи и дела');

-- --------------------------------------------------------

--
-- Структура таблицы `task_history`
--

CREATE TABLE `task_history` (
  `history_id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `changed_by` int(11) NOT NULL,
  `action_type` enum('create','update','complete','delete') COLLATE utf8mb4_unicode_ci NOT NULL,
  `changed_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`changed_fields`)),
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `user`
--

CREATE TABLE `user` (
  `id` int(11) NOT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  `access_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `surname` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `patronymic` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tel` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `user`
--

INSERT INTO `user` (`id`, `username`, `password`, `email`, `created_at`, `updated_at`, `access_token`, `surname`, `name`, `patronymic`, `tel`) VALUES
(11, 'admin', '$2y$13$eo/2Ja1.zNmij.jfM/lev.GBkCgvFuPBKpxnwuIUh5qhpBEJiLmCm', 'elewwlec@mail.ru', 1740806160, 1740806160, '4LPhJvuRuBET7UQtdzsY8GS23ArQFWVd', 'Пахарьков', 'Никита', 'Владимирович', ''),
(12, 'admin1', '$2y$13$GgeIw2S.Wbrh6hCH74xpR./nqSCA2usJ1wuSDR4iDNmt0gY4AltXO', 'elewwlec@mail.ru', 1740806214, 1740806214, 'gIbzbM3ZM_t84v0ikkrFoQW0_dn6-5lJ', 'Чеботина', 'Юлия', '', ''),
(13, 'ad', '$2y$13$xKqWinObL8Eu/dr.CEv4OeZZ.NYWvS2k/5UUWP3Mw1nx5UGLGooEy', 'elewwlec@mail.ru', 1741118034, 1741118034, 'VpYKbPMT29KKZENIP-XbqpKuPAjOkpE7', 'кепекп', 'епкепк', 'екпкепк', '+7 (464) 654-65-76'),
(14, '1', '$2y$13$7hud0GWz8lAK9Y/9R1yHIuQpVsdDMLU0AC6QUG0ShGLTF5/J9xgl6', 'elewwlec@mail.ru', 1741526502, 1741526502, 'kOO21N4o26BAVfkpQuOz0mJ1jY3N0En4', '1', '1', '1', '+7 (464) 654-65-76'),
(15, '2', '$2y$13$8tH9lCD2W7GW/NXRt4FrdeZUgLdsOvtfoqUhB9w.GDj/WzS2yx5P6', 'elewwlec@mail.ru', 1741526558, 1741526558, 'YcFi8zSHbEeSjlunj774Es2EGDeyxxAG', '2', '2', '2', '+7 (464) 654-65-76'),
(16, 'alina', '$2y$13$BkDFa1XN0hTphtW1HA7TV.pQ0QVUsZ8Z2ovYd/K8dQcsohpCcL3nS', 'elewwlec@mail.ru', 1750772855, 1750772855, '0M6LK-HD2NXTPWMNCWKhnXPjR4_zdKZL', '33', '33', '33', '+7 (464) 654-65-76');

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar_color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT '#7e57c2',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password_hash`, `first_name`, `last_name`, `avatar_color`, `created_at`, `updated_at`) VALUES
(1, 'nisha', 'nisha@gmail.com', '$2a$10$xJwL5v5zLSTQ7s4VUQ9QeOc6Xv7nN7yY8dKjQWkZ5tJvR1mW3f4bG', 'Ниша', 'Мандаринов', '#7e57c2', '2025-05-28 09:33:24', '2025-06-19 05:49:15');

-- --------------------------------------------------------

--
-- Структура таблицы `user_settings`
--

CREATE TABLE `user_settings` (
  `setting_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `theme` enum('light','dark') COLLATE utf8mb4_unicode_ci DEFAULT 'light',
  `timezone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'UTC',
  `daily_reminder_time` time DEFAULT NULL,
  `weekly_report` tinyint(1) DEFAULT 1,
  `email_notifications` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`event_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `task_id` (`task_id`);

--
-- Индексы таблицы `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`project_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `reminders`
--
ALTER TABLE `reminders`
  ADD PRIMARY KEY (`reminder_id`),
  ADD KEY `task_id` (`task_id`);

--
-- Индексы таблицы `subtasks`
--
ALTER TABLE `subtasks`
  ADD PRIMARY KEY (`subtask_id`),
  ADD KEY `task_id` (`task_id`);

--
-- Индексы таблицы `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`task_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Индексы таблицы `task_categories`
--
ALTER TABLE `task_categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Индексы таблицы `task_history`
--
ALTER TABLE `task_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `task_id` (`task_id`),
  ADD KEY `changed_by` (`changed_by`);

--
-- Индексы таблицы `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Индексы таблицы `user_settings`
--
ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `events`
--
ALTER TABLE `events`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `projects`
--
ALTER TABLE `projects`
  MODIFY `project_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `reminders`
--
ALTER TABLE `reminders`
  MODIFY `reminder_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `subtasks`
--
ALTER TABLE `subtasks`
  MODIFY `subtask_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `tasks`
--
ALTER TABLE `tasks`
  MODIFY `task_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT для таблицы `task_categories`
--
ALTER TABLE `task_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `task_history`
--
ALTER TABLE `task_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `user`
--
ALTER TABLE `user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT для таблицы `user_settings`
--
ALTER TABLE `user_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `events_ibfk_2` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`task_id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `reminders`
--
ALTER TABLE `reminders`
  ADD CONSTRAINT `reminders_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`task_id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `subtasks`
--
ALTER TABLE `subtasks`
  ADD CONSTRAINT `subtasks_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`task_id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`project_id`) REFERENCES `projects` (`project_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tasks_ibfk_3` FOREIGN KEY (`category_id`) REFERENCES `task_categories` (`category_id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `task_history`
--
ALTER TABLE `task_history`
  ADD CONSTRAINT `task_history_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`task_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `task_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `user_settings`
--
ALTER TABLE `user_settings`
  ADD CONSTRAINT `user_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
