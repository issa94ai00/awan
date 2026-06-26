-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: 26 يونيو 2026 الساعة 06:26
-- إصدار الخادم: 10.4.32-MariaDB
-- PHP Version: 8.4.21

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `attakadom2`
--

-- --------------------------------------------------------

--
-- بنية الجدول `addresses`
--

CREATE TABLE `addresses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `street` varchar(255) NOT NULL,
  `city` varchar(255) NOT NULL,
  `country` varchar(255) NOT NULL,
  `zip` varchar(255) NOT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `analytics_data_points`
--

CREATE TABLE `analytics_data_points` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `metric_id` bigint(20) UNSIGNED NOT NULL,
  `warehouse_id` bigint(20) UNSIGNED DEFAULT NULL,
  `channel_id` bigint(20) UNSIGNED DEFAULT NULL,
  `recorded_date` date NOT NULL,
  `value` decimal(20,4) NOT NULL,
  `dimensions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dimensions`)),
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `analytics_metrics`
--

CREATE TABLE `analytics_metrics` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `metric_key` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `name_ar` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `category` enum('sales','inventory','warehouse','financial','customer','operational') NOT NULL,
  `data_type` enum('number','percentage','currency','count','duration') NOT NULL,
  `aggregation` enum('sum','avg','count','min','max','last') NOT NULL,
  `unit` varchar(255) DEFAULT NULL,
  `calculation_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`calculation_config`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `attendances`
--

CREATE TABLE `attendances` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `employee_id` bigint(20) UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `clock_in` datetime DEFAULT NULL,
  `clock_out` datetime DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'present',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `entity_type` varchar(255) DEFAULT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `description` text DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(255) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `module` varchar(255) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `carts`
--

CREATE TABLE `carts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `carts`
--

INSERT INTO `carts` (`id`, `session_id`, `user_id`, `created_at`, `updated_at`) VALUES
(1, 'pvLQ1vZDX4azJw66kjjJ1OOInhY0XLUkjMEF3jd4', NULL, '2026-06-26 02:12:32', '2026-06-26 02:12:32');

-- --------------------------------------------------------

--
-- بنية الجدول `cart_items`
--

CREATE TABLE `cart_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `cart_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `categories`
--

CREATE TABLE `categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `name_ar` varchar(255) DEFAULT NULL,
  `name_en` varchar(255) DEFAULT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `icon` varchar(255) NOT NULL DEFAULT 'fa-box',
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `product_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `parent_id` bigint(20) UNSIGNED DEFAULT NULL,
  `description_en` text DEFAULT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` varchar(255) DEFAULT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `categories`
--

INSERT INTO `categories` (`id`, `created_at`, `updated_at`, `name_ar`, `name_en`, `slug`, `icon`, `description`, `image`, `product_count`, `sort_order`, `is_active`, `parent_id`, `description_en`, `meta_title`, `meta_description`, `is_featured`) VALUES
(1, '2026-03-09 12:07:27', '2026-04-18 23:03:45', 'حلول البناء المعمارية | Architectural Building Solutions', 'Architectural Building Solutions', 'architectural-building-solutions', 'fa-building', 'حلول شاملة للبناء المعماري بما في ذلك فتحات السطح، حواجز الدرج، وأنظمة الحماية.', 'categories/nSYg8nTI1s65gK4JZrfN8Vq0vRsw1ZAkCjc6yGiY.jpg', 23, 1, 1, NULL, NULL, NULL, NULL, 0),
(2, '2026-03-09 12:07:27', '2026-04-19 15:45:02', 'أنظمة إدارة الكابلات | Cable Management Systems', 'Cable Management Systems', 'cable-management-systems', 'fa-network-wired', 'حلول متكاملة لإدارة وتنظيم الكابلات الكهربائية.', 'categories/SyARNqxAOqSZvyeT1FwmnidTZ58c0UIF1qNuoaVG.jpg', 6, 3, 1, NULL, NULL, NULL, NULL, 0),
(3, '2026-03-09 12:07:27', '2026-04-19 15:46:03', 'إكسسوارات البلوك والطينة ( اللياسة) | Blockwork & Plastering Accessories', 'Blockwork & Plastering Accessories', 'blockwork-plastering-accessories', 'fa-trowel-bricks', 'إكسسوارات متخصصة لأعمال البلوك والجص والتشطيبات.', 'categories/YPBrPxODAFQRe7qhJdwPmFByndTeDLX3xX3KdBQs.jpg', 11, 4, 1, NULL, NULL, NULL, NULL, 0),
(4, '2026-03-09 12:07:27', '2026-04-19 15:46:46', 'إكسسوارات قوالب الخرسانة | Concrete Formwork Accessories', 'Concrete Formwork Accessories', 'concrete-formwork-accessories', 'fa-cube', 'معدات وأدوات خاصة بأعمال قوالب الخرسانة.', 'categories/5JxNPJkb8kMX12BqIEoiARc5m6kEzAxziifkdrWq.jpg', 11, 5, 1, NULL, NULL, NULL, NULL, 0),
(5, '2026-03-09 12:07:27', '2026-04-19 15:47:03', 'العزل المائي والحراري | Waterproofing & Thermal Insulation', 'Waterproofing & Thermal Insulation', 'waterproofing-thermal-insulation', 'fa-shield-alt', 'مواد العزل المائي والحراري بأعلى معايير الجودة.', 'categories/IBu63lmZdr6I9m7LmmmHKLknQrPaQSjI8qbQ3JaB.jpg', 14, 6, 1, NULL, NULL, NULL, NULL, 0),
(6, '2026-03-09 12:07:27', '2026-04-19 15:47:38', 'مشابك الأنابيب والعلاقات | Pipe Clamps, Hangers & Fixings', 'Pipe Clamps, Hangers & Fixings', 'pipe-clamps-hangers-fixings', 'fa-link', 'أنظمة تثبيت متكاملة للأنابيب والكوابل.', 'categories/zffAity6jclpRbeBA4ONoRm2nbYgswJAICT4IKup.jpg', 9, 7, 1, NULL, NULL, NULL, NULL, 0),
(7, '2026-03-09 12:07:27', '2026-04-19 15:49:48', 'القواطع الجبسية والأسقف المعلقة | Gypsum Partitions & Suspended Ceilings', 'Gypsum Partitions & Suspended Ceilings', 'gypsum-partitions-suspended-ceilings', 'fa-border-all', 'حلول جبسية عصرية للمساحات الداخلية.', NULL, 15, 8, 1, NULL, NULL, NULL, NULL, 0),
(8, '2026-03-09 12:07:27', '2026-04-19 15:51:19', 'إكسسوارات الكلادينج والواجهات | Cladding & Facade Accessories', 'Cladding & Facade Accessories', 'cladding-facade-accessories', 'fa-layer-group', 'نظام متكامل لتكسية الواجهات بأعلى المعايير.', 'categories/7wnnhokCPp0WSg0AqVBGYsPYlIITVjwARRX5vWfh.jpg', 7, 9, 1, NULL, NULL, NULL, NULL, 0),
(9, '2026-03-09 12:07:27', '2026-04-25 13:57:44', 'المواد الاستهلاكية والأدوات | Consumable Items & Hardware', 'Consumable Items & Hardware', 'consumable-items-hardware', 'fa-screwdriver-wrench', 'تشكيلة واسعة من الأدوات المهنية والمواد الاستهلاكية.', 'categories/3l21K1OOTa0NNkkL5X4ayICw6utiF2YmwTrJPBlS.jpg', 6, 11, 1, NULL, NULL, NULL, NULL, 0),
(10, '2026-04-19 15:44:41', '2026-04-19 15:55:11', 'حلول هندسية لأعمال المعادن Metal Work Engineering Solution', 'Metal Work Engineering Solution', 'metal-work-engineering-solution', 'fa-folder', 'حلول هندسية متكاملة لأعمال المعادن تشمل التصميم والتصنيع والتركيب للهياكل المعدنية والدرابزين والأبواب والسياجات. نقدم لك منتجات عالية الجودة مصممة بدقة لتناسب مشاريعك السكنية والتجارية والصناعية مع ضمان المتانة والأمان.', 'categories/Hw0fYYhHg483eLsWRcQaTTZfhp4lhA8MDleUnXXW.jpg', 0, 2, 1, NULL, NULL, NULL, NULL, 0),
(11, '2026-04-19 17:38:20', '2026-04-25 13:57:55', 'مواد سباكة وصحية Plumbing and sanitary materials', 'Plumbing and sanitary materials', 'plumbing-and-sanitary-materials', 'fa-folder', 'مجموعة متكاملة من مواد السباكة والصحية عالية الجودة، تشمل الأنابيب، الوصلات، المحابس، المغاسل، والمراحيض. تتميز بمقاومة ممتازة للتسرب والصدأ والتآكل، بتصاميم عصرية تناسب جميع المشاريع السكنية والتجارية والصناعية.', 'categories/6jXxk3UCTkzZEnTjxHBVWeV6Zx8aXFU85M1VTv0O.jpg', 0, 12, 1, NULL, NULL, NULL, NULL, 0),
(12, '2026-04-25 13:55:47', '2026-04-25 13:57:07', 'التجهيزات المرورية وسلامة الطرق (Traffic Safety & Control)', 'Traffic Safety & Control', 'traffic-safety-control', 'fa-folder', 'مجموعة متكاملة من تجهيزات السلامة المرورية والتحكم في حركة المرور، تشمل الحواجز، المطبات الصناعية، العواكس، اللوحات الإرشادية، والأعمدة الواقية. تتميز بجودة عالية، مقاومة للظروف الجوية والصدمات، لضمان أعلى مستويات الأمان على الطرق والمواقف والمنشآت.\r\n\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', NULL, 0, 10, 1, NULL, NULL, NULL, NULL, 0);

-- --------------------------------------------------------

--
-- بنية الجدول `companies`
--

CREATE TABLE `companies` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `contact` varchar(255) NOT NULL,
  `address` text NOT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `contacts`
--

CREATE TABLE `contacts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `contactable_type` varchar(255) NOT NULL,
  `contactable_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `position` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `customers`
--

CREATE TABLE `customers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `tax_number` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `state` varchar(255) DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `postal_code` varchar(255) DEFAULT NULL,
  `source` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `balance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `credit_limit` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(255) NOT NULL DEFAULT 'SAR',
  `total_purchases` decimal(14,2) NOT NULL DEFAULT 0.00,
  `last_purchase_at` date DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `auth_token` varchar(80) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `cycle_counts`
--

CREATE TABLE `cycle_counts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `warehouse_id` bigint(20) UNSIGNED NOT NULL,
  `bin_id` bigint(20) UNSIGNED DEFAULT NULL,
  `count_number` varchar(255) NOT NULL,
  `type` enum('full','partial','abc','blind') NOT NULL DEFAULT 'partial',
  `status` enum('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `counter_id` bigint(20) UNSIGNED DEFAULT NULL,
  `reviewer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `total_items` int(11) NOT NULL DEFAULT 0,
  `variance_items` int(11) NOT NULL DEFAULT 0,
  `variance_value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `requires_adjustment` tinyint(1) NOT NULL DEFAULT 0,
  `adjustment_by` bigint(20) UNSIGNED DEFAULT NULL,
  `adjusted_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `cycle_count_items`
--

CREATE TABLE `cycle_count_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `cycle_count_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `product_variant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `bin_id` bigint(20) UNSIGNED DEFAULT NULL,
  `expected_quantity` int(11) NOT NULL,
  `counted_quantity` int(11) NOT NULL,
  `variance` int(11) NOT NULL DEFAULT 0,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `variance_value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `variance_reason` enum('theft','damage','data_entry','unknown') DEFAULT NULL,
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `dashboards`
--

CREATE TABLE `dashboards` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `name_ar` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `type` enum('executive','sales','inventory','warehouse','financial','custom') NOT NULL,
  `layout_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`layout_config`)),
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 0,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `dashboard_widgets`
--

CREATE TABLE `dashboard_widgets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `dashboard_id` bigint(20) UNSIGNED NOT NULL,
  `metric_id` bigint(20) UNSIGNED DEFAULT NULL,
  `report_id` bigint(20) UNSIGNED DEFAULT NULL,
  `widget_type` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `title_ar` varchar(255) DEFAULT NULL,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`config`)),
  `position_x` int(11) NOT NULL DEFAULT 0,
  `position_y` int(11) NOT NULL DEFAULT 0,
  `width` int(11) NOT NULL DEFAULT 4,
  `height` int(11) NOT NULL DEFAULT 3,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `employees`
--

CREATE TABLE `employees` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `job_title` varchar(255) DEFAULT NULL,
  `department` varchar(255) DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `salary` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `employment_type` varchar(255) NOT NULL DEFAULT 'full_time',
  `contract_type` varchar(255) NOT NULL DEFAULT 'permanent',
  `bonus` decimal(12,2) NOT NULL DEFAULT 0.00,
  `national_id` varchar(255) DEFAULT NULL,
  `nationality` varchar(255) DEFAULT NULL,
  `contract_start` date DEFAULT NULL,
  `contract_end` date DEFAULT NULL,
  `emergency_contact_name` varchar(255) DEFAULT NULL,
  `emergency_contact_phone` varchar(255) DEFAULT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `bank_account_number` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `inquiries`
--

CREATE TABLE `inquiries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` enum('new','read','replied') NOT NULL DEFAULT 'new',
  `priority` enum('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `assigned_to` bigint(20) UNSIGNED DEFAULT NULL,
  `product_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `inquiries`
--

INSERT INTO `inquiries` (`id`, `user_id`, `name`, `email`, `phone`, `subject`, `message`, `status`, `priority`, `assigned_to`, `product_id`, `ip_address`, `user_agent`, `closed_at`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, NULL, 'mohammad youssef', 'issa94ai00@gmail.com', '0949179672', 'product_inquiry', 'test', 'read', 'medium', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, '2026-03-11 04:33:35', '2026-03-11 04:35:35', NULL),
(2, NULL, 'محمد عيسى عبدو', 's94z@hotmail.com', '0991979829', 'delivery', 'test \r\ncheep', 'read', 'medium', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, '2026-03-11 04:36:16', '2026-03-11 04:37:13', NULL);

-- --------------------------------------------------------

--
-- بنية الجدول `inquiry_replies`
--

CREATE TABLE `inquiry_replies` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `inquiry_id` bigint(20) UNSIGNED NOT NULL,
  `admin_id` bigint(20) UNSIGNED DEFAULT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `integration_settings`
--

CREATE TABLE `integration_settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `channel_name` varchar(255) NOT NULL,
  `api_domain` varchar(255) NOT NULL,
  `access_token` text NOT NULL,
  `sync_stock` tinyint(1) NOT NULL DEFAULT 1,
  `sync_orders` tinyint(1) NOT NULL DEFAULT 1,
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `inventory_transfers`
--

CREATE TABLE `inventory_transfers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `from_warehouse_id` bigint(20) UNSIGNED NOT NULL,
  `to_warehouse_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('pending','in_transit','completed','cancelled') NOT NULL DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `shipped_at` timestamp NULL DEFAULT NULL,
  `received_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `shipped_by` bigint(20) UNSIGNED DEFAULT NULL,
  `received_by` bigint(20) UNSIGNED DEFAULT NULL,
  `transfer_number` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `inventory_transfer_items`
--

CREATE TABLE `inventory_transfer_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `transfer_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `product_variant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `quantity_requested` int(11) NOT NULL,
  `quantity_shipped` int(11) NOT NULL DEFAULT 0,
  `quantity_received` int(11) NOT NULL DEFAULT 0,
  `batch_number` varchar(255) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `unit_cost` decimal(10,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `invoices`
--

CREATE TABLE `invoices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `invoice_number` varchar(255) NOT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tax` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(255) NOT NULL DEFAULT 'cash',
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `customer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sales_order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `paid_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `due_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(255) NOT NULL DEFAULT 'SAR',
  `exchange_rate` decimal(10,4) NOT NULL DEFAULT 1.0000,
  `due_date` date DEFAULT NULL,
  `reference` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `invoice_items`
--

CREATE TABLE `invoice_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `invoice_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED DEFAULT NULL,
  `product_unit_id` bigint(20) UNSIGNED DEFAULT NULL,
  `unit_name` varchar(255) DEFAULT NULL,
  `unit_multiplier` decimal(10,2) NOT NULL DEFAULT 1.00,
  `product_name` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `journal_entries`
--

CREATE TABLE `journal_entries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `entry_date` date NOT NULL,
  `ledger_account_id` bigint(20) UNSIGNED NOT NULL,
  `description` text DEFAULT NULL,
  `debit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `credit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `reference` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `currency` varchar(255) NOT NULL DEFAULT 'SAR'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `landed_costs`
--

CREATE TABLE `landed_costs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `purchase_receipt_id` bigint(20) UNSIGNED NOT NULL,
  `shipping_charges` decimal(10,2) NOT NULL DEFAULT 0.00,
  `customs_duties` decimal(10,2) NOT NULL DEFAULT 0.00,
  `insurance_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `other_charges` decimal(10,2) NOT NULL DEFAULT 0.00,
  `allocation_method` varchar(255) NOT NULL DEFAULT 'value',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `leave_requests`
--

CREATE TABLE `leave_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `employee_id` bigint(20) UNSIGNED NOT NULL,
  `leave_type` varchar(255) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `reason` text DEFAULT NULL,
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `ledger_accounts`
--

CREATE TABLE `ledger_accounts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `account_type` varchar(255) NOT NULL DEFAULT 'asset',
  `currency` varchar(255) NOT NULL DEFAULT 'SAR',
  `opening_balance` decimal(14,2) NOT NULL DEFAULT 0.00,
  `is_system` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2026_02_28_205418_create_categories_table', 1),
(5, '2026_02_28_205431_create_products_table', 1),
(6, '2026_02_28_205457_create_inquiries_table', 1),
(7, '2026_02_28_205522_create_settings_table', 1),
(8, '2026_02_28_205538_create_site_visitors_table', 1),
(9, '2026_03_05_000001_add_fields_to_categories_table', 1),
(10, '2026_03_05_000002_add_fields_to_products_table', 1),
(11, '2026_03_05_000003_add_fields_to_site_visitors_table', 1),
(12, '2026_03_05_000004_add_slug_to_categories_table', 1),
(13, '2026_03_05_000006_add_fields_to_inquiries_table', 1),
(14, '2026_03_05_000007_add_is_admin_to_users_table', 1),
(15, '2026_03_08_010320_add_settings_columns_to_settings_table', 1),
(16, '2026_03_11_100000_create_visitors_table', 2),
(17, '2026_03_10_180000_add_seo_to_products_table', 3),
(18, '2026_03_12_211948_add_show_price_to_products_table', 3),
(19, '2026_03_01_000001_create_addresses_table', 4),
(20, '2026_03_01_000002_create_payment_methods_table', 4),
(21, '2026_03_01_000003_create_orders_table', 4),
(22, '2026_03_01_000004_create_order_items_table', 4),
(23, '2026_03_01_000005_create_wallet_transactions_table', 4),
(24, '2026_03_01_000006_create_reviews_table', 4),
(25, '2026_03_01_000007_create_wishlist_items_table', 4),
(26, '2026_03_01_000008_create_notifications_table', 4),
(27, '2026_03_01_000009_add_flutter_fields_to_users_table', 4),
(28, '2026_05_07_135541_create_personal_access_tokens_table', 4),
(29, '2026_05_08_000000_create_invoices_table', 4),
(30, '2026_05_08_000001_create_invoice_items_table', 4),
(31, '2026_05_28_120000_create_suppliers_table', 4),
(32, '2026_05_28_120001_create_purchase_orders_table', 4),
(33, '2026_05_28_120002_create_purchase_order_items_table', 4),
(34, '2026_05_28_120003_create_employees_table', 4),
(35, '2026_05_28_120004_create_attendance_table', 4),
(36, '2026_05_28_120005_create_leave_requests_table', 4),
(37, '2026_05_28_120006_create_customers_table', 4),
(38, '2026_05_28_120007_create_tickets_table', 4),
(39, '2026_05_28_120008_create_ledger_accounts_table', 4),
(40, '2026_05_28_120009_create_journal_entries_table', 4),
(41, '2026_05_28_120010_create_stock_movements_table', 4),
(42, '2026_05_30_000000_create_companies_table', 4),
(43, '2026_05_31_230508_create_production_orders_table', 4),
(44, '2026_05_31_231923_create_payments_table', 4),
(45, '2026_05_31_231923_create_quotes_table', 4),
(46, '2026_05_31_231923_create_sales_orders_table', 4),
(47, '2026_05_31_231927_create_payrolls_table', 4),
(48, '2026_05_31_231927_create_purchase_receipts_table', 4),
(49, '2026_05_31_232317_create_quote_items_table', 4),
(50, '2026_05_31_232320_create_sales_order_items_table', 4),
(51, '2026_05_31_232322_create_purchase_receipt_items_table', 4),
(52, '2026_05_31_232727_add_balance_fields_to_customers_table', 4),
(53, '2026_05_31_232731_add_balance_fields_to_suppliers_table', 4),
(54, '2026_05_31_232732_add_sales_order_fields_to_invoices_table', 4),
(55, '2026_05_31_235159_add_stock_quantity_to_products_table', 4),
(56, '2026_05_31_235353_add_missing_invoice_columns', 4),
(57, '2026_05_31_235817_ensure_balance_columns_exist', 4),
(58, '2026_06_01_004656_create_roles_and_permissions_tables', 4),
(59, '2026_06_03_000001_add_avatar_to_employees_table', 4),
(60, '2026_06_03_000001_create_inquiry_replies_table', 4),
(61, '2026_06_03_000002_add_fields_to_inquiries_table', 4),
(62, '2026_06_12_132304_create_carts_table', 4),
(63, '2026_06_16_032137_erp_upgrade_add_erp_fields_to_all_tables', 4),
(64, '2026_06_17_000010_add_min_stock_to_products_table', 4),
(65, '2026_06_17_033053_add_customer_auth_fields_to_customers_table', 4),
(66, '2026_06_17_033103_create_subscribers_table', 4),
(67, '2026_06_19_024500_create_special_offers_table', 4),
(68, '2026_06_21_233000_add_performance_indexes_to_tables', 4),
(69, '2026_06_22_002821_add_language_fields_to_categories_table', 4),
(70, '2026_06_22_203009_add_missing_fields_to_products_table', 4),
(71, '2026_06_22_230602_create_erp_retail_upgrade_tables', 4),
(72, '2026_06_22_233814_enhance_warehouses_table_for_multi_location', 4),
(73, '2026_06_22_233817_enhance_warehouse_inventory_for_batch_tracking', 4),
(74, '2026_06_22_233821_create_inventory_transfers_table', 4),
(75, '2026_06_22_233827_create_inventory_transfer_items_table', 4),
(76, '2026_06_22_233831_create_product_batches_table', 4),
(77, '2026_06_22_233833_create_product_serial_numbers_table', 4),
(78, '2026_06_22_233837_create_reorder_alerts_table', 4),
(79, '2026_06_23_002012_create_order_channels_table', 4),
(80, '2026_06_23_002012_create_sales_contracts_table', 4),
(81, '2026_06_23_002016_enhance_sales_orders_for_multi_channel', 4),
(82, '2026_06_23_003002_create_picking_lists_table', 4),
(83, '2026_06_23_003003_create_picking_list_items_table', 4),
(84, '2026_06_23_003004_create_packing_lists_table', 4),
(85, '2026_06_23_003005_create_packing_list_items_table', 4),
(86, '2026_06_23_003006_create_shipping_manifests_table', 4),
(87, '2026_06_23_003007_create_shipping_manifest_items_table', 4),
(88, '2026_06_23_003008_create_cycle_counts_table', 4),
(89, '2026_06_23_003009_create_cycle_count_items_table', 4),
(90, '2026_06_23_004000_create_analytics_metrics_table', 4),
(91, '2026_06_23_004001_create_analytics_data_points_table', 4),
(92, '2026_06_23_004002_create_reports_table', 4),
(93, '2026_06_23_004003_create_dashboards_table', 4),
(94, '2026_06_23_004004_create_dashboard_widgets_table', 4),
(95, '2026_06_23_172040_create_notification_preferences_table', 4),
(96, '2026_06_23_172042_create_notification_templates_table', 4),
(97, '2026_06_23_175431_create_workflows_table', 4),
(98, '2026_06_23_175433_create_workflow_steps_table', 4),
(99, '2026_06_23_175436_create_workflow_executions_table', 4),
(100, '2026_06_23_175639_create_audit_logs_table', 4),
(101, '2026_06_24_000001_create_rma_requests_table', 4),
(102, '2026_06_24_000001_drop_redundant_customer_fields_from_invoices_table', 4),
(103, '2026_06_24_000002_create_rma_items_table', 4),
(104, '2026_06_24_000002_optimize_database_indexes', 4),
(105, '2026_06_24_000003_apply_partitioning_to_site_visitors_table', 4),
(106, '2026_06_24_000004_update_rma_requests_table_structure', 4),
(107, '2026_06_24_000005_add_rma_columns_safely', 4),
(108, '2026_06_24_020000_add_missing_rma_columns', 4),
(109, '2026_06_25_000001_add_barcode_to_product_units_table', 4),
(110, '2026_06_25_000002_add_unit_fields_to_invoice_items_table', 4),
(111, '2026_06_26_000001_create_product_units_table', 4);

-- --------------------------------------------------------

--
-- بنية الجدول `notifications`
--

CREATE TABLE `notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'info',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `notification_preferences`
--

CREATE TABLE `notification_preferences` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `notification_type` varchar(255) NOT NULL DEFAULT 'all',
  `email_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `sms_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `push_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `in_app_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `channels` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`channels`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `notification_templates`
--

CREATE TABLE `notification_templates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `template_key` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `name_ar` varchar(255) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `subject_ar` varchar(255) DEFAULT NULL,
  `body` text NOT NULL,
  `body_ar` text DEFAULT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'email',
  `variables` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`variables`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `orders`
--

CREATE TABLE `orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `order_number` varchar(255) NOT NULL,
  `status` enum('pending','processing','shipped','delivered','canceled','returned') NOT NULL DEFAULT 'pending',
  `subtotal` decimal(10,2) NOT NULL,
  `shipping_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL,
  `shipping_address_id` bigint(20) UNSIGNED NOT NULL,
  `payment_method_id` bigint(20) UNSIGNED DEFAULT NULL,
  `payment_method_type` varchar(255) NOT NULL DEFAULT 'card',
  `shipped_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `order_channels`
--

CREATE TABLE `order_channels` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `name_ar` varchar(255) DEFAULT NULL,
  `code` varchar(255) NOT NULL,
  `type` enum('online','retail','b2b','marketplace','api') NOT NULL,
  `integration_type` varchar(255) DEFAULT NULL,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `auto_sync` tinyint(1) NOT NULL DEFAULT 0,
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `order_items`
--

CREATE TABLE `order_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `product_title` varchar(255) NOT NULL,
  `product_image` varchar(255) NOT NULL,
  `product_brand` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `price_after_discount` decimal(10,2) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `size` varchar(255) DEFAULT NULL,
  `color` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `packing_lists`
--

CREATE TABLE `packing_lists` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `warehouse_id` bigint(20) UNSIGNED NOT NULL,
  `picking_list_id` bigint(20) UNSIGNED NOT NULL,
  `sales_order_id` bigint(20) UNSIGNED NOT NULL,
  `list_number` varchar(255) NOT NULL,
  `status` enum('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `packer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `total_packages` int(11) NOT NULL DEFAULT 0,
  `total_weight` decimal(10,2) NOT NULL DEFAULT 0.00,
  `dimensions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dimensions`)),
  `box_type` varchar(255) DEFAULT NULL,
  `packing_instructions` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `packing_list_items`
--

CREATE TABLE `packing_list_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `packing_list_id` bigint(20) UNSIGNED NOT NULL,
  `picking_list_item_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `product_variant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `package_number` varchar(255) DEFAULT NULL,
  `dimensions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dimensions`)),
  `weight` decimal(10,2) DEFAULT NULL,
  `fragile` tinyint(1) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `payments`
--

CREATE TABLE `payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `payment_number` varchar(255) NOT NULL,
  `invoice_id` bigint(20) UNSIGNED DEFAULT NULL,
  `customer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `payment_method` enum('cash','card','bank_transfer','check') NOT NULL DEFAULT 'cash',
  `status` enum('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_date` date DEFAULT NULL,
  `reference` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `currency` varchar(255) NOT NULL DEFAULT 'SAR',
  `exchange_rate` decimal(10,4) NOT NULL DEFAULT 1.0000,
  `sales_order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `purchase_order_id` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `payment_methods`
--

CREATE TABLE `payment_methods` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `card_number_last4` varchar(255) NOT NULL,
  `cardholder_name` varchar(255) NOT NULL,
  `expiry_date` varchar(255) NOT NULL,
  `card_type` varchar(255) NOT NULL DEFAULT 'visa',
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `payrolls`
--

CREATE TABLE `payrolls` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `payroll_number` varchar(255) NOT NULL,
  `employee_id` bigint(20) UNSIGNED DEFAULT NULL,
  `pay_period_start` date DEFAULT NULL,
  `pay_period_end` date DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `basic_salary` decimal(10,2) NOT NULL DEFAULT 0.00,
  `overtime_hours` decimal(8,2) NOT NULL DEFAULT 0.00,
  `overtime_rate` decimal(10,2) NOT NULL DEFAULT 0.00,
  `overtime_pay` decimal(10,2) NOT NULL DEFAULT 0.00,
  `bonuses` decimal(10,2) NOT NULL DEFAULT 0.00,
  `deductions` decimal(10,2) NOT NULL DEFAULT 0.00,
  `net_salary` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','processed','paid') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `permissions`
--

CREATE TABLE `permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `display_name` varchar(255) DEFAULT NULL,
  `module` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `display_name`, `module`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'dashboard.view', 'عرض لوحة التحكم', 'dashboard', NULL, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(2, 'categories.view', 'عرض الفئات', 'categories', NULL, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(3, 'categories.create', 'إنشاء فئة', 'categories', NULL, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(4, 'categories.edit', 'تعديل فئة', 'categories', NULL, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(5, 'categories.delete', 'حذف فئة', 'categories', NULL, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(6, 'products.view', 'عرض المنتجات', 'products', NULL, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(7, 'products.create', 'إنشاء منتج', 'products', NULL, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(8, 'products.edit', 'تعديل منتج', 'products', NULL, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(9, 'products.delete', 'حذف منتج', 'products', NULL, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(10, 'inquiries.view', 'عرض الاستفسارات', 'inquiries', NULL, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(11, 'inquiries.reply', 'الرد على الاستفسارات', 'inquiries', NULL, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(12, 'inquiries.delete', 'حذف استفسار', 'inquiries', NULL, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(13, 'settings.view', 'عرض الإعدادات', 'settings', NULL, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(14, 'settings.edit', 'تعديل الإعدادات', 'settings', NULL, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(15, 'users.view', 'عرض المستخدمين', 'users', NULL, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(16, 'users.create', 'إنشاء مستخدم', 'users', NULL, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(17, 'users.edit', 'تعديل مستخدم', 'users', NULL, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(18, 'users.delete', 'حذف مستخدم', 'users', NULL, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(19, 'roles.view', 'عرض الأدوار', 'roles', NULL, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(20, 'roles.create', 'إنشاء دور', 'roles', NULL, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(21, 'roles.edit', 'تعديل دور', 'roles', NULL, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(22, 'roles.delete', 'حذف دور', 'roles', NULL, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(23, 'permissions.view', 'عرض الصلاحيات', 'permissions', NULL, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(24, 'permissions.assign', 'تعيين صلاحيات', 'permissions', NULL, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(25, 'reports.view', 'عرض التقارير', 'reports', NULL, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(26, 'sales.view', 'عرض المبيعات', 'sales', NULL, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(27, 'sales.create', 'إنشاء مبيعات', 'sales', NULL, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(28, 'inventory.view', 'عرض المخزون', 'inventory', NULL, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(29, 'inventory.manage', 'إدارة المخزون', 'inventory', NULL, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(30, 'purchases.view', 'عرض المشتريات', 'purchases', NULL, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(31, 'purchases.create', 'إنشاء مشتريات', 'purchases', NULL, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(32, 'hr.view', 'عرض الموارد البشرية', 'hr', NULL, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(33, 'hr.manage', 'إدارة الموارد البشرية', 'hr', NULL, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46');

-- --------------------------------------------------------

--
-- بنية الجدول `permission_role`
--

CREATE TABLE `permission_role` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `permission_id` bigint(20) UNSIGNED NOT NULL,
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `permission_role`
--

INSERT INTO `permission_role` (`id`, `permission_id`, `role_id`, `created_at`, `updated_at`) VALUES
(1, 3, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(2, 5, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(3, 4, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(4, 2, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(5, 1, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(6, 33, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(7, 32, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(8, 12, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(9, 11, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(10, 10, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(11, 29, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(12, 28, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(13, 24, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(14, 23, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(15, 7, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(16, 9, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(17, 8, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(18, 6, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(19, 31, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(20, 30, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(21, 25, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(22, 20, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(23, 22, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(24, 21, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(25, 19, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(26, 27, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(27, 26, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(28, 14, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(29, 13, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(30, 16, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(31, 18, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(32, 17, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(33, 15, 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46');

-- --------------------------------------------------------

--
-- بنية الجدول `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) UNSIGNED NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `personal_access_tokens`
--

INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES
(1, 'App\\Models\\User', 1, 'api_token', '8d2f9cb020cd471b35190e5b220efa5e0e9bfdc5970f47e14bf5e57c630d39f7', '[\"*\"]', '2026-06-26 03:25:08', NULL, '2026-06-26 02:12:28', '2026-06-26 03:25:08');

-- --------------------------------------------------------

--
-- بنية الجدول `picking_lists`
--

CREATE TABLE `picking_lists` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `warehouse_id` bigint(20) UNSIGNED NOT NULL,
  `sales_order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `list_number` varchar(255) NOT NULL,
  `priority` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `status` enum('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `picker_id` bigint(20) UNSIGNED DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `total_items` int(11) NOT NULL DEFAULT 0,
  `picked_items` int(11) NOT NULL DEFAULT 0,
  `route` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`route`)),
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `picking_list_items`
--

CREATE TABLE `picking_list_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `picking_list_id` bigint(20) UNSIGNED NOT NULL,
  `sales_order_item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `product_variant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `bin_id` bigint(20) UNSIGNED DEFAULT NULL,
  `quantity_to_pick` int(11) NOT NULL,
  `quantity_picked` int(11) NOT NULL DEFAULT 0,
  `status` enum('pending','picked','short','cancelled') NOT NULL DEFAULT 'pending',
  `barcode` varchar(255) DEFAULT NULL,
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  `picked_at` timestamp NULL DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `production_orders`
--

CREATE TABLE `production_orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_number` varchar(255) NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `status` enum('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `products`
--

CREATE TABLE `products` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `category_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name_ar` varchar(255) DEFAULT NULL,
  `name_en` varchar(255) DEFAULT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `description_ar` text DEFAULT NULL,
  `description_en` text DEFAULT NULL,
  `short_description_ar` text DEFAULT NULL,
  `short_description_en` text DEFAULT NULL,
  `seo` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`seo`)),
  `price` decimal(10,2) DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `tax_rate` decimal(10,2) DEFAULT NULL,
  `taxable` tinyint(1) NOT NULL DEFAULT 0,
  `unit` varchar(50) DEFAULT NULL,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `min_stock` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `reorder_point` int(11) DEFAULT NULL,
  `show_price` tinyint(1) NOT NULL DEFAULT 1,
  `image_main` varchar(255) DEFAULT NULL,
  `image_gallery` longtext DEFAULT NULL,
  `brand` varchar(255) DEFAULT NULL,
  `model` varchar(255) DEFAULT NULL,
  `sku` varchar(255) DEFAULT NULL,
  `barcode` varchar(255) DEFAULT NULL,
  `color` varchar(255) DEFAULT NULL,
  `size` varchar(255) DEFAULT NULL,
  `variant_group` varchar(255) DEFAULT NULL,
  `in_stock` tinyint(1) NOT NULL DEFAULT 1,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `views_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `max_stock` int(11) DEFAULT NULL,
  `weight` decimal(8,2) DEFAULT NULL,
  `length` decimal(10,2) DEFAULT NULL,
  `width` decimal(10,2) DEFAULT NULL,
  `height` decimal(10,2) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `products`
--

INSERT INTO `products` (`id`, `created_at`, `updated_at`, `category_id`, `name_ar`, `name_en`, `slug`, `description_ar`, `description_en`, `short_description_ar`, `short_description_en`, `seo`, `price`, `cost_price`, `tax_rate`, `taxable`, `unit`, `stock_quantity`, `min_stock`, `reorder_point`, `show_price`, `image_main`, `image_gallery`, `brand`, `model`, `sku`, `barcode`, `color`, `size`, `variant_group`, `in_stock`, `is_featured`, `views_count`, `sort_order`, `max_stock`, `weight`, `length`, `width`, `height`, `is_active`) VALUES
(1, '2026-03-09 12:07:27', '2026-04-25 13:51:52', 1, 'فتحة الاسطح للطوارئ والصيانة', 'Roof Hatch', 'architectural-building-solutions-roof-hatch', 'فتحة أسطح مصنوعة من الألمنيوم أو الفولاذ المجلفن، توفر وصولاً آمناً وسهلاً إلى الأسطح لأعمال الطوارئ والصيانة والتهوية. تصميم محكم الإغلاق يمنع تسرب المياه والهواء، مع نظام قفل أمان ومفصلات متينة تتحمل الظروف الجوية القاسية.\r\n\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Roof hatch made of aluminum or galvanized steel, providing safe and easy access to roofs for emergency, maintenance, and ventilation work. Tight-sealing design prevents water and air leakage, with a safety locking system and durable hinges that withstand harsh weather conditions.\r\n\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 1981.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/NVn1cQuVX4qEv4GVZu5HKAOL0oovIy6e4Su8sNaA.jpg', '[]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(2, '2026-03-09 12:07:27', '2026-04-19 15:55:30', 10, 'أنظمة حواجز الدرج', 'Handrail Systems', 'architectural-building-solutions-handrail-systems', 'أنظمة حواجز درج متينة ومصنوعة من مواد عالية الجودة (ستانلس ستيل، حديد، ألمنيوم)، توفر الأمان والثبات على السلالم والمنحدرات. تصميمات عصرية تناسب المباني السكنية والتجارية، مع سهولة التركيب ومقاومة ممتازة للصدأ والتآكل.\r\n\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Durable handrail systems made of high-quality materials (stainless steel, iron, aluminum), providing safety and stability on stairs and ramps. Modern designs suitable for residential and commercial buildings, with easy installation and excellent resistance to rust and corrosion.\r\n\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 2006.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/x410CqLrzhYGNE2EN8kAL3kHyhWpea5jUuSmsADH.jpg', '[]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(3, '2026-03-09 12:07:27', '2026-04-25 13:52:17', 1, 'نظام مرمى النفايات والبياضات', 'Garbage & Linen Chutes', 'architectural-building-solutions-garbage-linen-chutes', 'نظام مرمى متكامل للنفايات والبياضات، مصنوع من مواد مقاومة للحريق والصدمات، يستخدم في الفنادق والمستشفيات والمباني السكنية. يوفر حلاً نظيفاً وسريعاً لنقل النفايات والغسيل بين الطوابق مع عزل تام للروائح والضوضاء.\r\n\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Integrated garbage and linen chute system made of fire and impact-resistant materials, used in hotels, hospitals, and residential buildings. Provides a clean, fast solution for transporting waste and laundry between floors with complete odor and noise isolation.\r\n\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 3498.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/v7hV5EvHSJIJMJBVsokg5QR9rB6vUjPwqg3ilosc.jpg', '[]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(4, '2026-03-09 12:07:27', '2026-04-25 13:42:24', 1, 'أرضية مرتفعة للوصول', 'Access Raised Floor', 'architectural-building-solutions-access-raised-floor', 'أرضية مرتفعة قابلة للإزالة تسمح بالوصول السهل إلى الكابلات والأنابيب وأنظمة التبريد تحت الأرض. مثالية لمراكز البيانات وغرف الخوادم والمكاتب التقنية، توفر تهوية ممتازة ومرونة كاملة في إعادة التوزيع.\r\n\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Removable raised access floor allowing easy access to cables, pipes, and cooling systems beneath the surface. Ideal for data centers, server rooms, and technical offices, providing excellent ventilation and complete reconfiguration flexibility.\r\n\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 1894.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/EOm8fJLc0rqWIMQoOUGse7TFZgAbc1qSMfZ5bPuG.jpg', '[]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(5, '2026-03-09 12:07:27', '2026-04-25 13:42:59', 1, 'الخزائن', 'Lockers', 'architectural-building-solutions-lockers', 'خزائن معدنية متينة متعددة الأحجام والألوان، مثالية لتخزين الأغراض الشخصية في المدارس والشركات والصالات الرياضية. تصميم مقاوم للصدمات والصدأ مع أقفال أمان موثوقة وتهوية جيدة.\r\n\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Durable metal lockers available in various sizes and colors, ideal for storing personal belongings in schools, offices, and gymnasiums. Impact and rust-resistant design with reliable safety locks and good ventilation.\r\n\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 905.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/jn48Fb4hSsuFho5skoHtEXmywNBLJRC9VPduGV0y.jpg', '[]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(6, '2026-03-09 12:07:27', '2026-04-25 13:41:17', 10, 'السلالم', 'Ladders', 'architectural-building-solutions-ladders', 'سلالم ألمنيوم ومعدنية عالية الجودة، خفيفة الوزن وقوية التحمل، مثالية للاستخدام في مواقع البناء والمستودعات والمنازل. تصميم مضاد للانزلاق مع أرجل ثابتة توفر لك الأمان والثبات أثناء العمل على الارتفاعات.\r\n\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'High-quality aluminum and metal ladders, lightweight yet strong, ideal for use on construction sites, warehouses, and homes. Anti-slip design with stable feet provides safety and stability when working at heights.\r\n\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 188.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/9mR85weeSX19NjdIPlzlgTEs1f2RnYR0F3m9N1Jf.webp', '[]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(7, '2026-03-09 12:07:27', '2026-04-25 13:43:09', 10, 'المشابك المعدنية (فولاذ / مجلفن)', 'Gratings (Steel / Galvanized)', 'architectural-building-solutions-gratings-steel-galvanized', 'مشابك معدنية (فولاذية ومجلفنة) عالية التحمل، تستخدم لتغطية فتحات التصريف وقنوات الصرف في المصانع والطرقات. تصميم مخرم يسمح بتصريف المياه مع قدرة على تحمل الأحمال الثقيلة ومقاومة ممتازة للصدأ والتآكل.\r\n\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Heavy-duty steel and galvanized gratings used for covering drainage openings and channels in factories and roadways. Perforated design allows water drainage while handling heavy loads with excellent resistance to rust and corrosion.\r\n\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 3783.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/yHj3gyoLCPAZ6iP5rUpKeATFK5e2REPSgfel2DES.jpg', '[]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(8, '2026-03-09 12:07:28', '2026-04-25 13:59:22', 10, 'الحواجز الفولاذية', 'Steel Bollards', 'architectural-building-solutions-steel-bollards', 'حواجز فولاذية متينة وعالية الجودة، مثالية لتنظيم حركة المركبات والمشاة وحماية الأماكن الحيوية. تصميم عصري ومرن للاستخدام الخارجي يتحمل الظروف القاسية ويوفر أماناً طويل الأمد.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'High-quality, durable steel barriers ideal for organizing vehicle and pedestrian traffic and protecting vital areas. A modern, flexible design for outdoor use that withstands harsh conditions and provides long-lasting safety.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 1131.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/USI9kmhhYJYeletqTFjG1T8HxLY8EHxBCkpaAq9O.jpg', '[]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(9, '2026-03-09 12:07:28', '2026-04-25 13:41:41', 10, 'السياجات', 'Fencing', 'architectural-building-solutions-fencing', 'سياجات معدنية متينة عالية الجودة، مثالية للمنازل والفلل والمنشآت التجارية والصناعية لتحديد الحدود وتوفير الأمان والخصوصية. تتميز بمقاومة ممتازة للصدأ والتآكل، سهلة التركيب، مع تصميمات عصرية متنوعة تناسب جميع الاحتياجات.\r\n\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'High-quality durable metal fencing, ideal for homes, villas, commercial and industrial facilities to define boundaries and provide security and privacy. Features excellent resistance to rust and corrosion, easy installation, with various modern designs to suit all needs.\r\n\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 1528.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/o0yGyGffyUZAPZ9KesVtPhoQezdVb5op5GBKtuMS.jpg', '[]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(10, '2026-03-09 12:07:28', '2026-04-19 15:56:50', 10, 'البوابات المعدنية', 'Metal Gates', 'architectural-building-solutions-metal-gates', 'بوابات معدنية متينة مصنوعة من الحديد أو الألمنيوم عالي الجودة، مثالية للمداخل الرئيسية والحدائق والمنشآت التجارية. تتميز بمقاومة ممتازة للصدأ والتآكل، تصميمات عصرية وقابلة للتخصيص، مع أنظمة قفل أمان متطورة تدوم طويلاً.\r\n\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Durable metal gates made of high-quality iron or aluminum, ideal for main entrances, gardens, and commercial facilities. Feature excellent resistance to rust and corrosion, modern customizable designs, and advanced safety locking systems for long-lasting performance.\r\n\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 872.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/0dMD7u5enlv3VxbuMUhGwdOo0JkxaJN0uUXXlJ1y.jpg', '[]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(11, '2026-03-09 12:07:28', '2026-04-25 13:49:34', 1, 'دواسات المداخل', 'Entrance Mats', 'architectural-building-solutions-entrance-mats', 'حافة درج من الألمنيوم عالية الجودة، تحمي حافة الدرج من التآكل والتلف وتوفر سطحاً مانعاً للانزلاق لزيادة الأمان. سهلة التركيب على جميع أنواع السلالم الخرسانية والخشبية والسيراميك، مع تصميم عصري يمنع الانزلاق ويطيل عمر الدرج.\r\n\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'High-quality aluminum stair nosing that protects the stair edge from wear and damage while providing an anti-slip surface for enhanced safety. Easy to install on all types of concrete, wooden, and ceramic stairs, with a modern design that prevents slipping and extends stair life.\r\n\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 4524.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/8CrbupoEdDk1mWKoNtLyLDVS5LFCrbSBbpPZ5vWO.jpg', '[]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(12, '2026-03-09 12:07:28', '2026-04-25 13:50:51', 1, 'حواجز المراحيض', 'Cubicle Toilet Partitions', 'architectural-building-solutions-cubicle-toilet-partitions', 'حواجز مراحيض عالية الجودة مصنوعة من مواد مقاومة للماء والرطوبة والصدمات، مثالية للاستخدام في الحمامات العامة والمرافق التجارية. تتميز بسهولة التركيب والتنظيف، مع تصميم أنيق يمنح الخصوصية والمتانة طويل الأمد.\r\n\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'High-quality toilet cubicle partitions made of water, moisture, and impact-resistant materials, ideal for use in public restrooms and commercial facilities. Feature easy installation and cleaning, with an elegant design providing privacy and long-lasting durability.\r\n\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 4421.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/tAKBWAuVB6nnz5K4mgzrJwLSchkci34mZHQonv9H.jpg', '[]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(13, '2026-03-09 12:07:28', '2026-04-25 13:50:30', 1, 'أنظمة مفاصل التمدد', 'Expansion Joint Systems', 'architectural-building-solutions-expansion-joint-systems', 'أنظمة مفاصل تمدد عالية الجودة لامتصاص الحركات الحرارية والاهتزازات في المباني والجسور والأرضيات. تمنع التشققات والتلف الهيكلي، وتوفر ختمًا مانعًا لتسرب الماء مع مرونة فائقة تتناسب مع جميع التطبيقات.\r\n\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'High-quality expansion joint systems for absorbing thermal movements and vibrations in buildings, bridges, and floors. Prevent cracks and structural damage, providing a watertight seal with superior flexibility suitable for all applications.\r\n\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 4638.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/CiREEeHaiE5narF7fJIqAVhyNrfX7eqIBYgONKwE.jpg', '[]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(14, '2026-03-09 12:07:28', '2026-04-25 13:50:12', 1, 'حافة الدرج', 'Stair Nosing', 'architectural-building-solutions-stair-nosing', 'حافة درج من الألمنيوم عالية الجودة، تحمي حافة الدرج من التآكل والتلف وتوفر سطحاً مانعاً للانزلاق لزيادة الأمان. سهلة التركيب على جميع أنواع السلالم الخرسانية والخشبية والسيراميك، مع تصميم عصري يمنع الانزلاق ويطيل عمر الدرج.\r\n\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'High-quality aluminum stair nosing that protects the stair edge from wear and damage while providing an anti-slip surface for enhanced safety. Easy to install on all types of concrete, wooden, and ceramic stairs, with a modern design that prevents slipping and extends stair life.\r\n\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 122.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/i534uINvPGcE5PT8HF6bk2FCYEEtDfdX5Z1rLuJX.jpg', '[]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(15, '2026-03-09 12:07:28', '2026-04-25 13:49:54', 1, 'حواف البلاط والسجاد', 'Tile & Carpet Trim', 'architectural-building-solutions-tile-carpet-trim', 'حواف ألمنيوم لتثبيت وإنهاء حواف البلاط والسجاد بشكل احترافي، تمنع التآكل والتلف عند نقاط الانتقال بين الأرضيات المختلفة. سهلة التركيب ومنحك مظهراً نظيفاً وعصرياً مع حماية تدوم طويلاً.\r\n\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Aluminum trims for professionally securing and finishing tile and carpet edges, preventing wear and damage at transition points between different flooring types. Easy to install, giving you a clean, modern look with long-lasting protection.\r\n\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 1618.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/ncsCHRd8Snw63dHNjgIkxWTVFeD0Yi9Z6T4bsNsB.jpg', '[]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(16, '2026-03-09 12:07:28', '2026-04-25 13:59:37', 12, 'مطب صناعي مطاطي', 'Speed Humps (Impact Protection)', 'architectural-building-solutions-corner-guard-impact-protection', 'مطبات صناعية مطاطية عالية الجودة لتهدئة سرعة المركبات في مواقف السيارات والمناطق السكنية والمدارس والمستشفيات. تصميم متين مقاوم للأشعة فوق البنفسجية والتآكل، مع عناصر عاكسة ليلاً لزيادة الأمان، سهلة التركيب والفك دون الحاجة لأدوات معقدة.\r\n\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'High-quality rubber speed humps for calming vehicle speed in parking lots, residential areas, schools, and hospitals. Durable design resistant to UV rays and corrosion, with reflective elements for night safety, easy to install and remove without complex tools.\r\n\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 3993.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/IFO4sxJV2kE7Ig1ZqdYM3ZxNMfLqKUcJYNupik84.png', '[]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(17, '2026-03-09 12:07:28', '2026-04-25 13:59:57', 12, 'حماية مطاطية للجدران (حماية من الصدمات)', 'Wall Guard (Impact Protection)', 'architectural-building-solutions-wall-guard-impact-protection', 'حماية مطاطية للجدران عالية المتانة، تمنع تلف الجدران الناتج عن صدمات عربات النقل والمعدات في المستودعات والجراجات والمستشفيات. سهلة التركيب، مقاومة للصدمات والتآكل، وتقلل تكاليف الصيانة بشكل كبير.\r\n\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'High-durability rubber wall guards preventing wall damage from impacts by transport carts and equipment in warehouses, garages, and hospitals. Easy to install, impact and abrasion resistant, significantly reducing maintenance costs.\r\n\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 954.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/G84On38DR7ErUKIvRYNG7fVcVnk5zhugvLPgXdao.jpg', '[]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(18, '2026-03-09 12:07:28', '2026-04-25 13:58:22', 12, 'حماية زوايا المطاط (حماية من الصدمات)', 'Rubber Corner Guard (Impact Protection)', 'architectural-building-solutions-rubber-corner-guard-impact-protection', 'حماية زوايا مطاطية عالية المتانة، تمنع تلف زوايا الجدران والأعمدة الناتج عن صدمات عربات النقل والمعدات في المستودعات والمستشفيات والجراجات. سهلة التركيب باللاصق أو البراغي، مقاومة للصدمات والتآكل، وتطيل عمر الدهان والجدران بشكل كبير.\r\n\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'High-durability rubber corner guards preventing damage to wall corners and columns from impacts by transport carts and equipment in warehouses, hospitals, and garages. Easy to install with adhesive or screws, impact and abrasion resistant, significantly extending paint and wall life.\r\n\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 4517.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/tmt1Zk0bppT3A3QDSYK3JL0Hsfw7M2FgdwAholpU.jpg', '[]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(19, '2026-03-09 12:07:28', '2026-04-25 13:58:31', 12, 'مانع انزلاق مطاطي للعجلة (حماية من الصدمات', 'Rubber Wheel Stopper (Impact Protection)', 'architectural-building-solutions-rubber-wall-guard-impact-protection', 'مانع انزلاق مطاطي عالي المتانة لمنع حركة المركبات في مواقف السيارات والجراجات، يحمي الجدران والأعمدة من الصدمات. تصميم مضاد للأشعة فوق البنفسجية والتآكل، مع عناصر عاكسة ليلاً، سهل التركيب ويمنع تلف الإطارات والهيكل.\r\n\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'High-durability rubber wheel stopper to prevent vehicle movement in parking lots and garages, protecting walls and columns from impacts. UV and corrosion-resistant design with reflective elements for night visibility, easy to install, prevents tire and chassis damage.\r\n\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 2580.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/7leKSqH1rSFPpVTIfFDe7aCAUHWXrzYh2c71dWUp.jpg', '[]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(20, '2026-03-09 12:07:28', '2026-04-25 13:58:46', 12, 'حاجز الرصيف (حاجز D)', 'Dock Fender (D Fender)', 'architectural-building-solutions-dock-fender-d-fender', 'حاجز رصيف مطاطي -اسمنتي على شكل حرف D لامتصاص الصدمات وحماية الأرصفة والجدران البحرية والساحات الصناعية من اصطدام المركبات والرافعات. تصميم عالي التحمل ومقاوم للتآكل والأشعة فوق البنفسجية، يوزع قوة الصدمات بشكل متساوٍ ويمنع الضرر الهيكلي.\r\n\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'D-shaped rubber-cement dock fender for absorbing impacts and protecting docks, seawalls, and industrial yards from collisions with vehicles and cranes. Heavy-duty design resistant to corrosion and UV rays, evenly distributes impact force and prevents structural damage.\r\n\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 1950.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/IzAQghkUxwz3W4F7ssVykiP4cpqLr5KZROQZ1MRh.webp', '[]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(23, '2026-03-09 12:07:28', '2026-04-25 13:40:32', 1, 'أغطية المنيوم لفواصل التمدد', 'Movement Joint Covers', 'architectural-building-solutions-movement-joint-covers', 'أغطية ألمنيوم عالية الجودة لتغطية وحماية فواصل التمدد في الأرضيات والجدران والأسقف. تصميم متين يسمح بحركة المبنى الطبيعية مع منع تراكم الأوساخ، سهلة التركيب ومقاومة للصدأ والتآكل، ومناسبة للمشاريع التجارية والصناعية.\r\n\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'High-quality aluminum covers for protecting and covering expansion joints in floors, walls, and ceilings. Durable design allows natural building movement while preventing dirt accumulation, easy to install, rust and corrosion resistant, suitable for commercial and industrial projects.\r\n\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 1169.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/zGYTeTGnicGzUQKsmUduzUHd9xjdw9FUsmgfqmsG.jpg', '[]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(24, '2026-03-09 12:07:29', '2026-04-16 12:30:11', 2, 'حامل كابلات مخرم', 'Cable Tray', 'cable-management-systems-cable-tray', 'حامل كابلات مخرم من الصلب المجلفن، يستخدم لتنظيم ودعم أسلاك وكابلات الكهرباء والاتصالات في المشاريع. تصميم مخرم يسمح بتهوية ممتازة وسهولة تركيب الكابلات مع صيانة بسيطة.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Perforated galvanized steel cable tray used for organizing and supporting electrical and communication cables in projects. Perforated design allows excellent ventilation and easy cable installation with simple maintenance.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 2017.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/uuLfJqlIZEX9pGNNYp0p7vpsYgaXXTa7R5Fmcnkw.jpg', '[\"products\\/gallery\\/mde1Q4OnfgzXd8oLf7PoK6XcJTgqxenGWlBXpgbw.jpg\",\"products\\/gallery\\/vKuJ0m7Mzgm0llLlb3Gg0NYSOsMNRvSZQEm2aIZI.jpg\",\"products\\/gallery\\/yT6iAFclik87OEx2qToiUgkGrr5I8qtjYv7LSMse.jpg\",\"products\\/gallery\\/HyMjuHxCAxDd4aOi4yJUvoZVUoloxxpBjFGD1MEI.jpg\",\"products\\/gallery\\/DbBsGLntJooyvOLaOTRa2qhgLSYr8w3FtxEB763W.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(25, '2026-03-09 12:07:29', '2026-04-16 12:44:24', 2, 'مجرى كابلات غير مخرم', 'Cable Trunking', 'cable-management-systems-cable-trunking', 'مجرى معدني غير مخرم مصمم خصيصاً لحماية وتنظيم كابلات الدش والإنترنت والألياف الضوئية داخل الأسقف. يمنع التشويش الكهرومغناطيسي، يوفر مظهراً نظيفاً، ويسهل إضافة أو صيانة الكابلات الدقيقة.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Non-perforated metal trunking specifically designed for protecting and organizing TV, internet, and fiber optic cables inside ceilings. Prevents electromagnetic interference, provides a clean look, and simplifies adding or maintaining delicate cables.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 1962.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/JEkfFYYgnoliQYqwoBnDItShrvmunvh8YwNVeWZx.jpg', '[\"products\\/gallery\\/BvbwBpgyHY6yQvMEQJ8Tc9bwHKgnUmgqLVCg8JDv.jpg\",\"products\\/gallery\\/pOlNYWB8UVhllE6y6ErkUfkCVagrO4atEGv9rMSp.jpg\",\"products\\/gallery\\/9IXoJZDDtv9Bjh1LwTUlTJctSBiWaOTt5MhykqbT.jpg\",\"products\\/gallery\\/ZTuUlX2y1m0Y8hLtRaEMbxgUJNokMGi6jCehrMH9.jpg\",\"products\\/gallery\\/qC2apPxIOnqkaqCJqQ34BbLSSb9ag7hvrAJvjufP.jpg\",\"products\\/gallery\\/nZRt5eufZiY1R2Cvlz8mJQVX9OkPPMuPmFeU9lOj.jpg\",\"products\\/gallery\\/c3xKRWXGAGIxJBOndRUOEw7FB4YBXaEoLcTsG3QC.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(26, '2026-03-09 12:07:29', '2026-04-16 12:34:32', 2, 'سلم وسلات لحمل الكابلات', 'Cable Ladder & Basket Tray', 'cable-management-systems-cable-ladder-basket-tray', 'سلم حديدي وسلة شبكية لحمل وتوزيع الكابلات الكهربائية في المشاريع الكبيرة والمباني الصناعية. تتميز بقوة تحمل عالية، تهوية ممتازة، وسهولة في التركيب والتعديل لتناسب جميع الأحجام.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Steel ladder and mesh basket tray for carrying and distributing electrical cables in large projects and industrial buildings. Feature high load capacity, excellent ventilation, and easy installation and adjustment to fit all sizes.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 1198.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/yXURwOvHivFZxbIoXqXtzyKEJ1b889RNT5AMt9Z4.jpg', '[\"products\\/gallery\\/kDZIKDlP3P47555D14KrMjgB34EkwpwvsbB86Bc5.jpg\",\"products\\/gallery\\/m7JFuKLFp0mfLkwiQh4dPQRWhtSQ6JUwrN3Z9nRK.jpg\",\"products\\/gallery\\/j0BRyxPDdv80EmcctCK42LrXCYM31YvI1biCyB7Y.jpg\",\"products\\/gallery\\/ztr23q4oaWuAG1OARqGzHyU9onnHPCdYTdmNs3ME.jpg\",\"products\\/gallery\\/Bx5FLQnZBtQ24AMC5T378rDrlimvVNK6BII9OfGa.jpg\",\"products\\/gallery\\/vnbMRskbPNotHBDXJQorF60Iv0ski7Fe1l6LebBp.webp\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(27, '2026-03-09 12:07:29', '2026-04-16 12:47:33', 2, 'صناديق لوحة التحكم', 'Control Panel Boxes', 'cable-management-systems-control-panel-boxes', 'صناديق لوحة تحكم معدنية متينة لحماية وتنظيم المفاتيح والقواطع والمعدات الكهربائية في المشاريع السكنية والصناعية. تتميز بمقاومة الصدأ، عزل تام، وتصميم يناسب التركيب الداخلي والخارجي.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Durable metal control panel boxes for protecting and organizing switches, circuit breakers, and electrical equipment in residential and industrial projects. Feature rust resistance, complete insulation, and a design suitable for indoor and outdoor installation.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 1840.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/aFpj6QBpyEmaE0YKzNxxa5kUZgDXfR1RFqYbTk60.jpg', '[\"products\\/gallery\\/bRUYPRI5sQnxjM8bno59mxZewlgqME9GVswyzThG.jpg\",\"products\\/gallery\\/hlRZPfI1E6FTEPIkYiXT27nqUcdH21LhLd0GFJbv.jpg\",\"products\\/gallery\\/07zUBSfdNtgAHEYvXnm5E7TT0ar92ntNfMXTrjE1.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(28, '2026-03-09 12:07:29', '2026-04-16 12:54:25', 2, 'الصناديق الكهربائية وأنابيب EMT', 'Electrical Boxes & EMT Conduits', 'cable-management-systems-electrical-boxes-emt-conduits', 'صناديق توزيع كهربائية وأنابيب EMT معدنية مجلفنة، تستخدم لحماية وتوجيه الأسلاك الكهربائية داخل الجدران والأسقف. تتميز بمقاومة الصدمات والصدأ، وسهولة التركيب والربط مع أنظمة التوزيع المختلفة.\r\n\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Galvanized metal electrical boxes and EMT conduits used for protecting and routing electrical wires inside walls and ceilings. Feature impact and rust resistance, and easy installation and connection with various distribution systems.\r\n\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 744.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/B7hvQrqFmg1BOIRvlzGqPNTHORhYmNkpmPN68FJJ.jpg', '[\"products\\/gallery\\/puQZKsDWfCdH7YpqBnHPRWnglhMmr8ecHV9yjD3t.jpg\",\"products\\/gallery\\/AiYbPov1HuE4T6yVuawc61WYnrC5x2QFcYA8OgPG.jpg\",\"products\\/gallery\\/923Kg2homrIvyLwZFNSw0abD5HqeNCCxxQ0dfWKV.jpg\",\"products\\/gallery\\/ZwJUoL0WxRabe3LraWdCKG3MctsUv8yoLC2k3OUP.jpg\",\"products\\/gallery\\/CmxIajGcsUshedNiiT4pklyDGPdELfRKnUXR5VkI.jpg\",\"products\\/gallery\\/mpeXlIMabl76i6vdmHgj6DqlsArOwgnmvkBNosnp.jpg\",\"products\\/gallery\\/BpuisH4APVFxX643AJu79iePikdnrDC9n3dq2str.jpg\",\"products\\/gallery\\/US8RswI48aZ1YSjB0bErwK0cwXmAsvc2AgQhvNar.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(29, '2026-03-09 12:07:29', '2026-04-16 12:59:24', 2, 'أنابيب UPVC للكهرباء', 'UPVC Conduits', 'cable-management-systems-upvc-conduits', 'أنابيب UPVC عازلة وغير قابلة للصدأ، تستخدم لحماية الأسلاك الكهربائية داخل الجدران والأسقف والأرضيات. خفيفة الوزن، سهلة القص والتركيب، وتوفر عزلاً كهربائياً ممتازاً وآمناً.\r\n\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'UPVC insulated, non-corrosive conduits used for protecting electrical wires inside walls, ceilings, and floors. Lightweight, easy to cut and install, providing excellent and safe electrical insulation.\r\n\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 273.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/OrqO0CNIAY4Wz93XWLwN832tnhNH7hrZarOt1a1Z.jpg', '[\"products\\/gallery\\/UWSxwPR94VSAreExp9nn1zYJDiWkmWm2XD1oanWn.jpg\",\"products\\/gallery\\/SAcBMs8XGhRyFJ4rKwbofWUu3PDSZFy9b6P2e24r.jpg\",\"products\\/gallery\\/aANeIQS8HDxJ1aa6po8nxa3gKcdmHklNSjWYrwh0.jpg\",\"products\\/gallery\\/OLtJ9wLdEqgEfCj9LTBLrYDbLb8byDDqJSginGRp.jpg\",\"products\\/gallery\\/SpBG1oZTs7jrk0DoEfvNWm6B5IoLykN5YzsRoiZR.jpg\",\"products\\/gallery\\/QNoeJoCOmNgp0ljOrXBWThNYULsVDjc9XGSlBemE.jpg\",\"products\\/gallery\\/J5YIfAi8jOGWvfPe4Lq00Z99eeegEk2f3ks6UpGh.jpg\",\"products\\/gallery\\/3K4ZzaILwl96xxqLKAkm2KmvrEA4TR0Dzt9fi8IC.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(30, '2026-03-09 12:07:29', '2026-04-16 13:07:40', 3, 'العتبات الحديدية الجاهزة الصنع', 'Block Lintel', 'blockwork-plastering-accessories-lintel-block-ties', 'عتبات حديدية جاهزة الصنع عالية التحمل، تستخدم لتوزيع الأحمال فوق فتحات الأبواب والنوافذ في الجدران الخرسانية والبلوك. تتميز بقوة شد ممتازة، سهلة التركيب، وتوفر دعماً هيكلياً آمناً وطويل الأمد.\r\n\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Pre-fabricated heavy-duty steel lintels used for distributing loads above door and window openings in concrete and block walls. Feature excellent tensile strength, easy installation, and provide safe, long-lasting structural support.\r\n\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 3114.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/q85hwNQg3Jz9WHNll8CXElNwDJwn0lJ2i6Dd25xI.jpg', '[\"products\\/gallery\\/76N8cAAMj5BOsc5i24lJZAMse5pX2O25ioJPUkQs.jpg\",\"products\\/gallery\\/aQNzGIcoHHsLbXptaiigcYS4LW20Uo0GIHlfaXpM.jpg\",\"products\\/gallery\\/4IP2hbu8Dyvc0BYNzOEKphG7oSswSWIDjwdEzCzW.jpg\",\"products\\/gallery\\/j2oT3Y73hpyYGFgO1OUStIhk3tXg6419MXDwIdR6.png\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(31, '2026-03-09 12:07:29', '2026-04-16 13:24:39', 3, 'تقوية البلوك (سلم وشبكة)', 'Block Reinforcement (Ladder & Mesh)', 'blockwork-plastering-accessories-block-reinforcement-ladder-mesh', 'شبكة وسلم حديدي مجلفن لتقوية جدران البلوك والخرسانة، يمنع التشققات ويزيد من مقاومة الجدران للقوى الجانبية والزلازل. سهل التركيب بين طبقات البلوك، يوفر هيكلاً قوياً ومتيناً.\r\n\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Galvanized steel ladder and mesh for reinforcing block and concrete walls, preventing cracks and increasing resistance to lateral forces and earthquakes. Easy to install between block layers, providing a strong and durable structure.\r\n\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 2756.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/9XsfU3A7FFdffhGwmMSXX4TTWYuawEbD0jFpqFaw.webp', '[\"products\\/gallery\\/2EszOeETuieyXnuUrBG3ZtQfHmd7CAZIv6AsP8aG.jpg\",\"products\\/gallery\\/klObmWrQyAmlYNL3CbCBSWb8pduvbF6ytYrlH5Al.jpg\",\"products\\/gallery\\/rOuNSJDDjClMmV6OIWKq8k4oN0KceeRFglCSwCPH.jpg\",\"products\\/gallery\\/K1585Asb8ordjfPsiVqDiMGzbk3mGnSigQYrwRp6.jpg\",\"products\\/gallery\\/nEHIxDLfKSsxUohJ2EpxnQeuKkOnp9PPgEdROc6c.jpg\",\"products\\/gallery\\/aM4shTX1uakfvBrZ1YApR6fIPZ1wNecxjV0kuulj.png\",\"products\\/gallery\\/2pnpgeKq0OPE2x76xHZjdG4AqydCZKQHuhBfPKyj.png\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(32, '2026-03-09 12:07:29', '2026-04-16 13:44:27', 3, 'زاوية الحافة للطينة (لياسة)', 'Plastering Corner Bead', 'blockwork-plastering-accessories-corner-bead', 'زاوية حافة معدنية مصممة لحماية وتقوية زوايا الحوائط والطينة الجبسية، تمنحك تشطيبًا ناعمًا وحافة مستقيمة واحترافية عالية. سهلة التركيب ومقاومة للصدمات.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Metal corner bead designed to protect and reinforce plaster and drywall corners, giving you a smooth finish and a perfectly straight, professional edge. Easy to install and impact-resistant.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 4450.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/3dZW3VnXuBKkaRHVGvYDa4ObEDL3Bv0bmtMkiTwU.jpg', '[\"products\\/gallery\\/wmj8UPdaFJZMT1SJapqjBUgKCLU1gdOttpO65wTQ.jpg\",\"products\\/gallery\\/TdGdT4A5luQajGSqVJGr5UexgCqpDl0lsHWOrEvp.jpg\",\"products\\/gallery\\/B8vLlHj378UOxBWonSlHAUDvNvrnmrl9AstEXkj1.jpg\",\"products\\/gallery\\/TDxqTJUdyk5R1XeCMugriIHFz4lVXAv73ajhIsnp.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(33, '2026-03-09 12:07:29', '2026-04-16 14:05:40', 3, 'شبكة الزاوية الداخلية للطينة  (لياسة)', 'Inside Plastering Corner Mesh', 'blockwork-plastering-accessories-corner-mesh', 'شبكة زاوية داخلية معدنية لتقوية وحماية زوايا اللياسة الداخلية، تمنع التشققات والانهيارات عند التقاء الجدران. مرنة وسهلة التركيب، تتناسب مع جميع أنواع الطينة (اللياسة)\r\n\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Metal inside corner mesh for reinforcing and protecting internal plaster corners, preventing cracks and breakdowns where walls meet. Flexible and easy to install, compatible with all types of compounds and plasters.\r\n\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 3717.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/FS5zvMicPdQ7LhZVy7yy2y8Jyw1lLBtqF0iVVovb.jpg', '[\"products\\/gallery\\/YRQVTHNoLVldWaB4Op28usqlPLQXLLQAOh2XuzdH.jpg\",\"products\\/gallery\\/0MgJhfe2uEwzoCbFaAFG1Oq40LzBdoBPuk1qcAbx.jpg\",\"products\\/gallery\\/P7rhjgBvUfOPEoQPVyi5OR7ojhGlgJcYmkYIN42Y.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(34, '2026-03-09 12:07:29', '2026-04-16 14:14:22', 3, 'حافة نهاية الطينة (لياسة)', 'Plaster Stop Bead', 'blockwork-plastering-accessories-plaster-stop-bead', 'حافة نهاية معدنية لتحديد وإنهاء طبقات اللياسة عند فتحات الأبواب والنوافذ والزوايا المكشوفة. تمنحك حافة مستقيمة ونظيفة، تحمي اللياسة من التفتت والتشقق عند نقاط النهاية.', 'Metal plaster stop bead for defining and finishing plaster layers at door openings, windows, and exposed corners. Gives you a clean, straight edge, protecting plaster from chipping and cracking at termination points.\r\n\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 915.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/SLq89T2nKwfJSV1vIZr2mlFs5sgkJjd4MiPjmrcL.jpg', '[\"products\\/gallery\\/ssFOCHWWBKvjQY6NnHzmRgjGnwblfjOd8H14RNGw.jpg\",\"products\\/gallery\\/DMgPlCdhs3aMmmoEUUIKvtDoH6mOcDAYpA6HoVbs.jpg\",\"products\\/gallery\\/3CyqBMMICQpau5mWwSEa3K2hK0hJzZgsy18bq1d9.jpg\",\"products\\/gallery\\/FBeR14ELzjQoJLifMPrNdWVLyHJvu7yGjHNh0eAv.jpg\",\"products\\/gallery\\/EYQBk1ZH8M7HdaUXSlMGU6Ee7UZqYIH1xhHnHo4i.jpg\",\"products\\/gallery\\/uIBU0OC5zpJ1Za9SFGZ0YFP67NBQErTuueYtowmK.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(35, '2026-03-09 12:07:29', '2026-04-16 14:17:43', 3, 'مفصل التحكم للطينة (لياسة)', 'Control Joint', 'blockwork-plastering-accessories-control-joint', 'مفصل تحكم معدني يستخدم لتقسيم مساحات اللياسة الكبيرة ومنع التشققات الناتجة عن التمدد والانكماش الحراري. يسمح بحركة طفيفة للجدار دون تصدع أو تلف السطح.\r\n\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Metal control joint used to divide large plaster areas and prevent cracks caused by thermal expansion and contraction. Allows slight wall movement without cracking or damaging the surface.\r\n\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 3593.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/YwWI4jUGpXX9TpRFELGsf9jJzzipfS9IanMYFfiy.jpg', '[\"products\\/gallery\\/3pTeSLYzpTxBMeQtIY2PjhLkUdKCmQwCJiqpiFEV.jpg\",\"products\\/gallery\\/SpUC03v6080Q4CdXT4BZBWvepgNVbkmytiYpngjC.jpg\",\"products\\/gallery\\/AecFR05tkddcQlcYE95oEjPmD7AE6KuHwmxJvkHO.jpg\",\"products\\/gallery\\/iyrBHFc4vFle1hbcopJRiEsI1qgoJh5FU78lfXJ7.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(36, '2026-03-09 12:07:29', '2026-04-16 14:20:40', 3, 'حافة الإطار للطينة (لياسة)', 'Architrave Bead', 'blockwork-plastering-accessories-architrave-bead', 'حافة إطار معدنية مصممة لتثبيت وإنهاء اللياسة حول إطارات الأبواب والنوافذ، تمنحك زوايا داخلية وخارجية مستقيمة ونظيفة. تخفي الفواصل بين الإطار والحائط وتمنع التشققات مع مرور الوقت.\r\n\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Metal architrave bead designed to secure and finish plaster around door and window frames, giving you straight, clean internal and external corners. Hides gaps between frame and wall and prevents cracks over time.\r\n\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 933.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/1005P2sjEalq9AteKmXQ11QW1eR1B0eO9vuRiltb.jpg', '[\"products\\/gallery\\/2YI78sGYRMIBxTEZYLDW0INx4Bbo5r7RB6ZSYIAJ.jpg\",\"products\\/gallery\\/m43gguWF35x25izHWws1yc1Zs9tlKiK3AwaXLcYX.jpg\",\"products\\/gallery\\/BzUyinWdAI4EhUxZbQ0C0JZo468nYjOr9pbyLg1q.jpg\",\"products\\/gallery\\/EOWOKfPALRLTck1Q02tad9YtfhKruszJU3ZUoRFP.jpg\",\"products\\/gallery\\/2i753tAj3A7lgECXczEsjNq9ioqkTGSZEEBi0gsv.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(37, '2026-03-09 12:07:29', '2026-04-18 21:30:15', 3, 'شبكة معدنية موسعة للطينة والصبيات', 'Expanded Metal Lath', 'blockwork-plastering-accessories-expanded-metal-lath', 'شبكة معدنية موسعة عالية الجودة، تستخدم كقاعدة لتثبيت الطينة (اللياسة) والصبيات الأسمنتية على الجدران والأسقف. تصميم مخرم يسمح باختراق المعجون والتصاق قوي، مقاومة للصدأ والتآكل، وتمنع التشققات والتساقط مع مرور الوقت.\r\n\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'High-quality expanded metal lath used as a base for securing plaster and cement screeds on walls and ceilings. Perforated design allows compound penetration and strong adhesion, rust and corrosion resistant, preventing cracks and falling over time.\r\n\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 3182.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/rRVa9NwQWri61XFF2kzK1eqQsI0WQ7ipA0flVX27.jpg', '[\"products\\/gallery\\/mvbtTYygffPQMy2kpMyt0Ae8CdpuSQYs0clnpbps.jpg\",\"products\\/gallery\\/nwNKGY1ETW7CxKRyap4zw020zy0ofa2KMaR6V6yc.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(38, '2026-03-09 12:07:29', '2026-04-18 21:33:01', 3, 'شبك حديد للطينة', 'Coil Lath', 'blockwork-plastering-accessories-coil-lath', 'شبك حديدي ملفوف عالي الجودة لتقوية الطينة (اللياسة) ومنع التشققات والتساقط على الجدران والأسقف. يتميز بمرونة عالية وسهولة التركيب، ومقاومة ممتازة للصدأ، ومناسب للاستخدام الداخلي والخارجي في جميع مشاريع البناء.\r\n\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'High-quality coiled iron lath for reinforcing plaster and preventing cracks and falling on walls and ceilings. Features high flexibility, easy installation, excellent rust resistance, suitable for indoor and outdoor use in all construction projects.\r\n\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 3115.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/gxNbvwdjxRDwKVkmxK9ubNvE8Mh9IDRb8NXAUDwh.jpg', '[\"products\\/gallery\\/52UHJ1FX9Crf1a1UktJYUo9LcLVLwDDbiAa34MCB.png\",\"products\\/gallery\\/N8Lz5Mps6MiqXGMeQZzcr1BblBcai3UvPPWSLBS3.jpg\",\"products\\/gallery\\/r4UxBepU8TR9e2g3YSR7ogbjEcpSTlQAZurA5cKu.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(40, '2026-03-09 12:07:29', '2026-04-18 21:38:17', 3, 'هاي-ريب (ورقة تقوية)', 'Hy-Rib (Reinforcement Sheet)', 'blockwork-plastering-accessories-hy-rib-reinforcement-sheet', 'ورقة تقوية من الصلب المجلفن (هاي-ريب) تستخدم كقاعدة دائمة لصب الخرسانة في الأسقف والجدران والأرضيات. تصميمها المضلع يضمن التصاقًا فائقًا بالخرسانة، يمنع التشققات ويزيد من قوة التحمل الهيكلية مع سهولة التركيب والتشكيل.\r\n\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Galvanized steel Hy-Rib reinforcement sheet used as a permanent formwork for pouring concrete in ceilings, walls, and floors. Its ribbed design ensures superior concrete adhesion, prevents cracks, increases structural load capacity, and offers easy installation and shaping.\r\n\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 2879.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/vIlTf0cAvn5FnsxhMGSBSB3PSBVXw4maMlCgENVt.jpg', '[\"products\\/gallery\\/lrxmfnOGpmJ84vPv23MeqDX0CYVEw0hcYTYBCGzk.jpg\",\"products\\/gallery\\/la4CIs3SrCyVdWhxEhOPAzM3z5w3MtkWne6kyRqj.jpg\",\"products\\/gallery\\/sSFNvBoIi4bWNeojKe1EfREf4laZ3bCLFtnM3v9s.jpg\",\"products\\/gallery\\/mncCYQ3TdQHvxZVXJT9BJDWsjLoaLnBRYCHAGbEW.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(41, '2026-03-09 12:07:29', '2026-04-12 19:03:18', 4, 'الخشب الرقائقي  بلاي وود', 'Plywood (Plywood Timber)', 'concrete-formwork-accessories-plywood-plywood-timber', 'منتج عالي الجودة من إكسسوارات قوالب الخرسانة | Concrete Formwork Accessories', NULL, NULL, NULL, NULL, 438.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/qRzPPhdNY4uJwNvkzJnPY7Rewhcy6tUT3v9qulsx.jpg', '[]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(42, '2026-03-09 12:07:29', '2026-04-18 21:20:40', 4, 'قضبان ربط الجدران الخرسانية', 'Tie Rods', 'concrete-formwork-accessories-tie-rods', 'قضبان ربط فولاذية عالية القوة، تستخدم لربط وتثبيت الجدران الخرسانية والقوالب أثناء الصب ومنع الانهيارات. تتميز بمقاومة ممتازة للشد والصدأ، وسهولة التركيب والفك، وتوفر دعماً هيكلياً آمناً ومتانة تدوم طويلاً.\r\n\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'High-strength steel tie rods used for securing and anchoring concrete walls and formwork during pouring, preventing collapses. Feature excellent tensile and rust resistance, easy installation and removal, providing safe structural support and long-lasting durability.\r\n\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 1236.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/8n87EoYNHqpYsxC8AB03oTH6tHPh0qYqjA1wgBYs.jpg', '[\"products\\/gallery\\/VprerBxecGcNxbCNZ4FwJJd1KN4gYNjpl9F2hJyT.jpg\",\"products\\/gallery\\/7NGM64HgWm7l6TU1wCjEbX99cRswWAyrTRSMSNu5.jpg\",\"products\\/gallery\\/ppr2c2KX30sqB8DygmSwOM1rQ4ccFWL0gkzVLz8Z.jpg\",\"products\\/gallery\\/jed77pguSexaBUpRBzfNCbeL5gAls4lJdaQ0Oiic.jpg\",\"products\\/gallery\\/K1IdAR7jdAliJg85sE0fKgmsAtc7FWZZXdJ8ALq9.jpg\",\"products\\/gallery\\/HcoT6KsoduizdRduneYkrmLIbNPdHL0sWORVvEu5.jpg\",\"products\\/gallery\\/h3U3uVdClEDC1qto85OJ2VxsWBmD1J6oQzHUcHAy.jpg\",\"products\\/gallery\\/SaNBaLGPzzYKD3laYsfvOEe0qZMOcnmrgEqKTSIB.jpg\",\"products\\/gallery\\/mpKfVQ3uqY03FW3zhWa1WkX8ui4W5OUCVc4wCp5F.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(45, '2026-03-09 12:07:30', '2026-04-14 16:38:54', 4, 'مانع  تسرب الماء للصبات الخرسانية', 'Waterstop', 'concrete-formwork-accessories-waterstop', 'شريط مانع تسرب مصنوع من PVC المرن عالي الجودة، يُدمج ضمن فواصل الصبات الخرسانية لمنع نفاذ الماء والرطوبة. مثالي للخزانات والمسابح والأساسات والأنفاق، يضمن لك ختمًا كاملاً وطويل الأمد.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Flexible, high-quality PVC waterstop strip embedded in concrete construction joints to prevent water and moisture penetration. Ideal for tanks, swimming pools, foundations, and tunnels, ensuring a complete and long-lasting seal.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 1642.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/MDjl6YrTexXbcpvVYW9KPc5n1dez0JJ37NfP9ctJ.jpg', '[\"products\\/gallery\\/ZHSGisnHMhcHhIS3JimK1WXxHI8iwAh0M62GXq0C.jpg\",\"products\\/gallery\\/YBiPepowZrT5pMGY9mB0lcVl6bLPlWzgp2eugxmG.jpg\",\"products\\/gallery\\/BanFaJQPbYOgBmX6vbnw0QBUA41yX3MaEqI6S6x7.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(46, '2026-03-09 12:07:30', '2026-04-14 16:40:32', 4, 'أنبوب PVC لصبات الخرسانة', 'PVC Pipe', 'concrete-formwork-accessories-pvc-pipe', 'أنبوب PVC عالي الجودة يستخدم كقنوات تمرير للكهرباء والسباكة داخل الصبات الخرسانية. يتميز بخفة الوزن، مقاومة التآكل والصدأ، وسهولة القص والتركيب قبل صب الخرسانة.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'High-quality PVC pipe used as passage conduits for electrical wiring and plumbing within concrete pours. Features light weight, corrosion and rust resistance, and easy cutting and installation before concrete pouring.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 1034.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/8pCX1WaXxYPLLLyWhF60q02MGOEDkNSkwNwgcwGR.jpg', '[\"products\\/gallery\\/Xcu38Uwnozoh5iBBKuEU19uLFIYVBILwHYq300te.jpg\",\"products\\/gallery\\/q8CNniyO82doo5OiLGyKRim7Yb5APSLNlQlDcAHJ.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(47, '2026-03-09 12:07:30', '2026-04-18 21:54:54', 9, 'لفائف PVC شرنك للتغليف', 'PVC Coil', 'concrete-formwork-accessories-pvc-coil', 'لفائف PVC شفافة وقابلة للانكماش الحراري، تستخدم لتغليف وحماية المنتجات والمواد الإنشائية من الغبار والرطوبة والخدوش. توفر تغليفًا محكمًا واحترافيًا يبرز منتجك مع حماية فائقة.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Transparent, heat-shrinkable PVC coils used for wrapping and protecting construction products and materials from dust, moisture, and scratches. Provide a tight, professional packaging that showcases your product with superior protection.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 2165.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/BnO9TEHVdmihntD6mb4JOEsHdP4Hnbpbmd9p099E.webp', '[]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(50, '2026-03-09 12:07:30', '2026-04-18 22:03:09', 4, 'مشبك سريع', 'Rapid Clamp', 'concrete-formwork-accessories-rapid-clamp', 'مشبك سريع أحادي الحركة مصنوع من الفولاذ المقاوم للصدأ، يتيح لك تثبيت وفك الأنابيب والقنوات بلمسة واحدة دون أدوات. مثيل للتركيبات المؤقتة والدائمة التي تتطلب سرعة وكفاءة عالية في العمل.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'One-touch rapid clamp made of stainless steel, allowing you to fasten and release pipes and channels instantly without tools. Ideal for temporary and permanent installations requiring high speed and efficiency.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 1692.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/fRG1wxaM9wdKjVp8mRiiRNm7Y5F3QUyAgdCB3KHw.jpg', '[\"products\\/gallery\\/GJBlJGZkHLXZ1a2LliDcb61y0EKsqVsRfX3Z0SzQ.jpg\",\"products\\/gallery\\/9O5NnmJSZjEZuYVhsCBViOhQurzGJBPCKvbIzaAr.jpg\",\"products\\/gallery\\/yED4kFKS7u0pXSGJxKf5rVao5SxL0Ebbt0OZBeev.jpg\",\"products\\/gallery\\/SRMtOngf2wySQKypd9FeJI70cD39q2jDKi6aOlo4.jpg\",\"products\\/gallery\\/bmK4LDJqXvlqPP4i7LVup9QyTl1O5dZ6IyVMwxNk.jpg\",\"products\\/gallery\\/VSOs4O2xueirE2Thj7X5Mvrs898v4X2NASQLNh4X.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1);
INSERT INTO `products` (`id`, `created_at`, `updated_at`, `category_id`, `name_ar`, `name_en`, `slug`, `description_ar`, `description_en`, `short_description_ar`, `short_description_en`, `seo`, `price`, `cost_price`, `tax_rate`, `taxable`, `unit`, `stock_quantity`, `min_stock`, `reorder_point`, `show_price`, `image_main`, `image_gallery`, `brand`, `model`, `sku`, `barcode`, `color`, `size`, `variant_group`, `in_stock`, `is_featured`, `views_count`, `sort_order`, `max_stock`, `weight`, `length`, `width`, `height`, `is_active`) VALUES
(51, '2026-03-09 12:07:30', '2026-04-18 21:56:51', 6, 'صامولة طويلة (موصل)', 'Long Nut (Connector)', 'concrete-formwork-accessories-long-nut-connector', 'منتج عالي الجودة من إكسسوارات قوالب الخرسانة | Concrete Formwork Accessories', NULL, NULL, NULL, NULL, 4199.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/IXhhNeePbcT2MMTA4oZeIWfhCXQTdge1Tj2qwPch.jpg', '[]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(52, '2026-03-09 12:07:30', '2026-04-14 16:49:42', 5, 'غشاء البيتومين', 'Bitumen Membrane', 'waterproofing-thermal-insulation-bitumen-membrane', 'غشاء بيتومين عالي الجودة لعزل الأسطح والأساسات ضد تسرب الماء والرطوبة. يتميز بمرونة عالية ومقاومة ممتازة للأشعة فوق البنفسجية، ويوفر طبقة حماية متينة وسهلة التركيب باللهب أو اللاصق الذاتي.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'High-quality bitumen membrane for waterproofing roofs and foundations against water and moisture ingress. Features high flexibility and excellent UV resistance, providing a durable protective layer that is easy to install by torch or self-adhesive.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 1619.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/S3brROGkhfcVFli8JriB8Oe3br2hGwxMHU6fm6um.jpg', '[]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(53, '2026-03-09 12:07:30', '2026-04-14 16:50:33', 5, 'سائل عزل برايمر', 'Liquid Membrane praimer', 'waterproofing-thermal-insulation-liquid-membrane', 'سائل برايمر عازل من البيتومين المعدل، يستخدم لتجهيز الأسطح الخرسانية والمعدنية قبل تطبيق أغشية العزل. يتميز بقوة التصاق عالية، سريع الجفاف، ويسد المسامات لضمان سطح مثالي للعزل.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Modified bitumen liquid primer used for preparing concrete and metal surfaces before applying waterproofing membranes. Features high adhesion, fast drying, and seals pores to ensure an ideal surface for insulation.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 234.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/fHX08viIci4X1w19RubaF7iff2jGVODKZBxJH2LJ.png', '[]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(54, '2026-03-09 12:07:30', '2026-04-14 16:53:04', 5, 'الواح فلر بوردللحماية وللحشوات', 'Filler Board', 'waterproofing-thermal-insulation-filler-board', 'لوح حشو عازل عالي الكثافة يستخدم لملء الفواصل والتجاويف في أنظمة العزل الحراري والمائي. يتميز بقوة ضغط ممتازة، مقاوم للرطوبة، وسهل القص والتركيب ليناسب مختلف التطبيقات.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'High-density insulating filler board used for filling joints and cavities in thermal and waterproofing systems. Features excellent compressive strength, moisture resistance, and easy cutting and installation for various applications.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 1685.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/ICxv83zZACboPFOPPRWXqmTBsO1ZWrOOjerHvlwk.jpg', '[\"products\\/gallery\\/5XSu3M0QWCTpAKEBnMXyhrs2jh7LoThGR63ewvjt.jpg\",\"products\\/gallery\\/DNaNNiXdwk6cRXN0dKloBrb5oR1xU35l9ZGH5vGw.jpg\",\"products\\/gallery\\/vAHbBF6ZoMYEoA5MFIOUhIMHyCBBs5ZP3BTSAjWL.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(55, '2026-03-09 12:07:30', '2026-04-14 16:56:27', 5, 'لوحة الحماية بولي بروبولين', 'Protection Board', 'waterproofing-thermal-insulation-protection-board', 'لوح حماية عالي المتانة يستخدم لتغطية وحماية أغشية العزل المائي من التلف أثناء ردم الخنادق أو صب الخرسانة. يتميز بمقاومة عالية للصدمات والضغط، ويطيل عمر نظام العزل بشكل كبير.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'High-durability protection board used to cover and protect waterproofing membranes from damage during backfilling or concrete pouring. Features high impact and compression resistance, significantly extending the life of the insulation system.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 2089.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/tnt5Owxa4zoWindBHMmUzaCbfUIy9Dpv3iUn6jVy.png', '[]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(57, '2026-03-09 12:07:30', '2026-04-14 17:00:01', 5, 'قماش الجيوتكستايل', 'Geotextile Fabric', 'waterproofing-thermal-insulation-geotextile-fabric', 'قماش جيوتكستايل غير منسوج وعالي المسامية، يستخدم للفصل والترشيح والتعزيز في مشاريع الطرق والأساسات وأنظمة الصرف الصحي. يمنع اختلاط التربة بالحصى مع السماح بتصريف المياه بكفاءة.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Non-woven, high-permeability geotextile fabric used for separation, filtration, and reinforcement in road, foundation, and drainage system projects. Prevents soil from mixing with gravel while allowing efficient water drainage.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 1852.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/71FPZFs3Emra2q3Go2NBQvfAbyuq7AIpRQ0lio51.jpg', '[\"products\\/gallery\\/o31YYPbDR68V76KU08Wyu6KipAelKJJzys6V22Au.jpg\",\"products\\/gallery\\/i6N6qMGuU7XrskD3grY7JjD0jsGniFmeopt0Tz4b.jpg\",\"products\\/gallery\\/qRj1LO4inOM5q1XN5TSk12iaXiwIAOVkhMTfjRzS.jpg\",\"products\\/gallery\\/ppcivczDuXjP7ShdkyOxLK772FPRrnTX9Mp1S738.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(58, '2026-03-09 12:07:30', '2026-04-14 17:13:14', 5, 'وزرة ألمنيوم', 'Aluminum Flashing', 'waterproofing-thermal-insulation-polyethylene-flashing', 'وزرة ألمنيوم مرنة ومقاومة للصدأ، تستخدم لحماية حواف الأسطح والمناطق المعرضة للماء والرطوبة. تتميز بخفة الوزن وسهولة التشكيل والتركيب، وتمنع تسرب المياه خلف الواجهات والنوافذ بشكل فعال.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Flexible, rust-resistant aluminum flashing used to protect roof edges and areas exposed to water and moisture. Features light weight, easy shaping and installation, effectively preventing water leakage behind facades and windows.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 4871.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/fZNS1E62463z4uBljA26diN2wy835xQ6Gn3hIWGn.jpg', '[\"products\\/gallery\\/KXl3UgwD7EfMADAE2dmtYwh8xFUXLNUBDJSNdeyl.jpg\",\"products\\/gallery\\/wwGbdu4LwMVdIyDQiptUBNP2VAeFRCMG2MHbuG3X.jpg\",\"products\\/gallery\\/gwJ4oBq5wM33pTYKHEUb6Tffyh85ua6iUYG3oASP.jpg\",\"products\\/gallery\\/gidTDDTAgxAltRYrtpBJVUO0CKbdULjCOC208GpQ.jpg\",\"products\\/gallery\\/jeFB56vKlvlUvft98TrArGrLvjzQQtZLWpjFcMUg.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(60, '2026-03-09 12:07:30', '2026-04-14 17:17:16', 5, 'خابور تثبيت يعمل بالطرق', 'Hammer Anchor', 'waterproofing-thermal-insulation-hammer-anchor', 'خابور تثبيت بالطرق مصمم خصيصًا لتثبيت طبقات العزل المائي والحراري على الأسطح الخرسانية. يُدق مباشرة ، ويوفر تثبيتًا آمنًا وسريعًا مع رأس عريض يمنع تمزق أغشية العزل.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Hammer drive anchor specifically designed for fixing waterproofing and thermal insulation layers to concrete surfaces. Hammered directly , providing secure and fast fastening with a wide head that prevents tearing of insulation membranes.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 788.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/031GSSrEJT2smUtl9pvzhlvX4k0vkQ3MgLDU10xN.jpg', '[\"products\\/gallery\\/D6VeYur5NNGtVcBZwycGOWA2hTBUHQb6NfnXTM2B.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(61, '2026-03-09 12:07:30', '2026-04-18 22:12:06', 9, 'ريش دريل(فردتخريم ) للحديد والخشب والخرسانة', 'Drill Bits (Steel-Concrete-Wood)', 'waterproofing-thermal-insulation-drill-bits', 'ريش دريل عالية الجودة متعددة الاستخدامات، مصممة لتخريم الحديد والخشب والخرسانة بدقة وسرعة. تتميز برؤوس كربيدية شديدة التحمل، مقاومة للتآكل والحرارة العالية، وتناسب جميع أنواع المثاقب الكهربائية والبطارية.\r\n\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'High-quality, multi-purpose drill bits designed for drilling through steel, concrete, and wood with precision and speed. Feature durable carbide tips, resistant to wear and high heat, suitable for all types of electric and cordless drills.\r\n\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 4395.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/haxDsj6eZ5p7qvjCtkT2gmVpN0eg4bLUhJ9oyz7F.jpg', '[\"products\\/gallery\\/MyfOn513bf5retuNR3nsVd7U8Zo6M1Yi3ihdSoiN.jpg\",\"products\\/gallery\\/AP9SkIj0NiQqvp2mg11hEhVZccfoSeGViSoJojmN.jpg\",\"products\\/gallery\\/B2Q4otnIF59xgeG61OnaBIpAQEFRLNQqBagWWKDE.jpg\",\"products\\/gallery\\/zPquZ2CmTd1uK7EyhzPZQKedB6DPwr1QcfJFcn5w.jpg\",\"products\\/gallery\\/3KyUFPXmVbtqMW75E0dBEdNpszMlQ8seh3buL3DJ.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(63, '2026-03-09 12:07:30', '2026-04-14 17:20:38', 5, 'بوليسترين مبثوق', 'Extruded Polystyrene', 'waterproofing-thermal-insulation-extruded-polystyrene-xps', 'ألواح بوليسترين مبثوق (XPS) عالية الكثافة، تستخدم للعزل الحراري للأساسات والأسطح والجدران الخارجية. تتميز بمقاومة ممتازة للرطوبة والضغط، وخفة الوزن، ومعامل توصيل حراري منخفض جدًا.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'High-density extruded polystyrene (XPS) boards used for thermal insulation of foundations, roofs, and exterior walls. Feature excellent moisture and compression resistance, light weight, and a very low thermal conductivity coefficient.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 2710.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/qsXBBj9E6c6dAdonxeDwhv2wBcqUtR6ufnn0MPrn.jpg', '[\"products\\/gallery\\/4jLqu84wVLRqBQA19V9u942cc1HsQG52wxktr86Q.jpg\",\"products\\/gallery\\/iHqGuMnUy0L13VlGMy1QQDDj683PDRMzVKlAnwc4.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(64, '2026-03-09 12:07:30', '2026-04-14 17:22:42', 5, 'بوليسترين موسع', 'Expanded Polystyrene', 'waterproofing-thermal-insulation-expanded-polystyrene-eps', 'ألواح بوليسترين موسع (EPS) خفيفة الوزن وعالية الكفاءة، تستخدم للعزل الحراري للجدران والأسقف والأرضيات. تتميز بمرونة ممتازة، مقاومة جيدة للرطوبة، وسهولة في القص والتركيب بأسعار اقتصادية.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Lightweight, high-efficiency expanded polystyrene (EPS) boards used for thermal insulation of walls, ceilings, and floors. Feature excellent flexibility, good moisture resistance, and easy cutting and installation at economical prices.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 4224.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/4UG9B2YlKOPsSoga4ecGG7w5FFxkgkKSUYaj1YbK.jpg', '[\"products\\/gallery\\/d5cNqGKNoUZscwHVxOmyEV11S5JZJge0Tr4PidgS.jpg\",\"products\\/gallery\\/RnaJZWvxtdt6EaOWetJ71MmKt5Ljr1NsqAAtzVtU.jpg\",\"products\\/gallery\\/9bpd3OAOggyFpUaPWURcArkY7DELpODX6VDNNE7j.jpg\",\"products\\/gallery\\/a6IPvisSkwtt6b2mibedpV4KzS3EBqHwu0BLHSF1.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(65, '2026-03-09 12:07:30', '2026-04-16 12:27:09', 5, 'الصوف الصخري', 'Rock Wool', 'waterproofing-thermal-insulation-rock-wool', 'ألواح الصوف الصخري عالية الكثافة، عازل حراري وصوتي ممتاز ومقاوم للحريق حتى 1000 درجة مئوية. يستخدم للأسقف والجدران والواجهات، ويمنحك توفيراً في الطاقة وحماية فائقة.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'High-density rock wool boards, an excellent thermal and acoustic insulator with fire resistance up to 1000°C. Used for roofs, walls, and facades, providing energy savings and superior protection.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 3723.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/VTAdB7neMICzDTQ9GnrOTdKZ0I5Gwisvc8QfZPij.jpg', '[]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(66, '2026-03-09 12:07:31', '2026-04-14 15:56:26', 6, 'مشبك الشريط', 'Strap Clamp', 'pipe-clamps-hangers-fixings-strap-clamp', 'مشبك شريطي فولاذي مرن وعالي التحمل، يستخدم لتعليق وتثبيت الأنابيب والقنوات الكهربائية بكفاءة. يتميز بسهولة الثني والتعديل ليتناسب مع مختلف الأحجام ويوفر تثبيتًا آمنًا ضد الاهتزاز.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Flexible, high-strength steel strap clamp used for efficiently hanging and securing pipes and electrical conduits. Features easy bending and adjustment to fit various sizes and provides secure vibration-resistant fastening.', NULL, NULL, NULL, 1634.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/HBrknXR8huejn650zKhuYMmdgsuShwu5TFENAYqW.jpg', '[\"products\\/gallery\\/Nmbf3evpaacKVd7Pw7YAPya7VPaw4GAL1QMUVHNs.jpg\",\"products\\/gallery\\/2IwHGDesXDOLLlrvGOoxdPFuJqDTmZovc3JqjfIR.jpg\",\"products\\/gallery\\/KEvHZmDyValA6k93KxSx1SLY2g0Z0wBvgGNrxgnh.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(67, '2026-03-09 12:07:31', '2026-04-14 15:51:10', 6, 'مشبك U', 'U-Clamp', 'pipe-clamps-hangers-fixings-u-clamp', 'مشبك U فولاذي متعدد الاستخدامات لتثبيت وتعليق الأنابيب والخراطيم والقنوات بسهولة وأمان. يتميز بقوة تحمل عالية وسهولة في التركيب ومقاومة ممتازة للاهتزاز والصدأ.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Versatile steel U-clamp for easily and securely fastening and hanging pipes, hoses, and channels. Features high load capacity, easy installation, and excellent resistance to vibration and rust.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 2786.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/UfNhEO0i1nIewbM7Dxl1YcGxLbe7aU9eCRlDVhTI.jpg', '[\"products\\/gallery\\/6VX3G3PZ0nGd7AUThLrS9JO5w4yfdS72VuYvxFgE.jpg\",\"products\\/gallery\\/cwrq734LZKPWMBMLf3nupNBRMjjjFRDSzd7tpKxz.jpg\",\"products\\/gallery\\/9e3aC6bD4iBwoSiTCgvWVWPPBFX5W6Wsll8qTNRM.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(68, '2026-03-09 12:07:31', '2026-04-14 15:54:08', 6, 'مشبك الأنابيب', 'Pipe Clamp', 'pipe-clamps-hangers-fixings-pipe-clamp', 'مشبك أنابيب معدني مزود بطبقة مطاطية داخلية لعزل الصدمات والاهتزازات وحماية الأنابيب من الاحتكاك. يوفر تثبيتًا آمنًا ومحكمًا مع تقليل الضوضاء وإطالة عمر الأنابيب.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Metal pipe clamp with an inner rubber lining to isolate shocks and vibrations and protect pipes from friction. Provides secure, tight fastening while reducing noise and extending pipe life.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 461.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/PERI42s3gLpKCF3U3HRvCECU2PilwuAcY4R35bjb.jpg', '[\"products\\/gallery\\/CeGTyirqDhzTPPJjoFOTnC8BrZM7Yc5O8oaOqajV.jpg\",\"products\\/gallery\\/SutDv0p6bUtsYUgNJJNaA4SmN4isM8tvOTOG5TSP.jpg\",\"products\\/gallery\\/AmvKR9ZSQa9LhxPGc14FD6aPfvUpX28N4beARzfH.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(69, '2026-03-09 12:07:31', '2026-04-14 15:58:54', 6, 'مشبك دوار', 'Swivel Clamp', 'pipe-clamps-hangers-fixings-swivel-clamp', 'مشبك دوار متعدد الزوايا مصنوع من الفولاذ المقاوم للصدأ، يسمح بتثبيت الأنابيب في أي اتجاه بفضل تصميمه القابل للدوران 360 درجة. مثالي للأماكن الضيقة والتركيبات المائلة والصعبة.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Multi-angle swivel clamp made of stainless steel, allows pipes to be secured in any direction thanks to its 360-degree rotatable design. Ideal for tight spaces, angled, and difficult installations.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 2658.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/CiH07ILlUFWSuPiVb7uNP1pF20STEVoD2rRDaSOD.jpg', '[\"products\\/gallery\\/MR3Rj8JJ4cMu6vJQdbCCebRIkPSgytW7syrcRG34.jpg\",\"products\\/gallery\\/ZNUwYuvIzLapECLg3azqQ8JAYzeCYEDJQXMq7cbq.jpg\",\"products\\/gallery\\/u6VHpvMoH8MBr07EU9XJmyOt8gdrflVISLpLLPk8.jpg\",\"products\\/gallery\\/SBVc7dwLmzRqTl9ABn4Y3oS59jLPGj6SXiGnU7nQ.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(70, '2026-03-09 12:07:31', '2026-04-14 16:01:33', 6, 'مشبك كليفيس', 'Clevis Clamp', 'pipe-clamps-hangers-fixings-clevis-clamp', 'مشبك كليفيس فولاذي مقوى على شكل حرف U، يستخدم لتعليق وتثبيت الأنابيب العمودية والأفقية في أنظمة التكييف والسباكة. يوفر مرونة في الحركة مع تثبيت آمن ومقاومة عالية للأحمال الثقيلة.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Reinforced steel clevis clamp in a U-shape, used for hanging and securing vertical and horizontal pipes in HVAC and plumbing systems. Provides flexibility with secure fastening and high resistance to heavy loads.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 696.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/qWufw2avUCsI1EOPaEewIFlsEV2qjEPxdbRY4Y8w.jpg', '[\"products\\/gallery\\/2GySNTr4rQbtdkl15Tle9T9V4o0tZ4vWrNRtgWaA.jpg\",\"products\\/gallery\\/tyO03hiIY3c8YOJjvAEqN7Lmo0s4SEyN0ilam8Dw.jpg\",\"products\\/gallery\\/nqi1gLIZV2tNp5zBt53cn5sVM9wOL9MRgJJ8JMZu.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(72, '2026-03-09 12:07:31', '2026-04-14 16:05:15', 6, 'كلبس القناة', 'Channel clamp', 'pipe-clamps-hangers-fixings-channel', 'كلبس قناة فولاذي متين يستخدم لتثبيت وتعليق القنوات المعدنية والأنابيب على الأسقف والجدران. يتميز بسهولة التركيب والضبط، ويوفر تثبيتًا آمنًا مقاومًا للاهتزاز والتآكل.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Durable steel channel clamp used for securing and hanging metal channels and pipes to ceilings and walls. Features easy installation and adjustment, providing secure fastening resistant to vibration and corrosion.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 1014.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/ojcBXNvt198vbZrLhyCuuEyCZ5RPdQx3xK0k4Av8.png', '[\"products\\/gallery\\/Fc7esGngLgydiJfIgwVmWWeTBhHAlBXSO3MUzw3P.jpg\",\"products\\/gallery\\/jYYemxYAHOcjd7rTkfwZ4SUKrR5mvtfICqzdvDBG.jpg\",\"products\\/gallery\\/3bq5L30xwAsxdcBVlU7lX6gwdSFHalOzyxhn3QAk.jpg\",\"products\\/gallery\\/5IVHSEh9mlLewwefpHdIAMO0tuQeTULmTpGSi6Xo.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(73, '2026-03-09 12:07:31', '2026-04-14 16:08:39', 6, 'مشبك العارضة', 'Beam Clamp', 'pipe-clamps-hangers-fixings-beam-clamp', 'مشبك عارضة فولاذي عالي التحمل، يُثبت مباشرة على العوارض الحديدية والخشبية دون حاجة للحفر. مثالي لتعليق الأنابيب والقنوات والمعدات الثقيلة بسرعة وأمان مع توزيع مثالي للأحمال.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Heavy-duty steel beam clamp that attaches directly to steel and wooden beams without drilling. Ideal for quickly and safely hanging pipes, channels, and heavy equipment with perfect load distribution.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 3288.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/0VLme8Bbdhdg1P4BfNTN5pBQFbItARl55bLhPSaL.jpg', '[\"products\\/gallery\\/UJlrS63smlq4mdywv3IXGkOJXRxIlnW4Uh22MSoi.jpg\",\"products\\/gallery\\/60BnVckCVp59vyguS5KiKjEcD5vVN1KDxR3Cu3DH.jpg\",\"products\\/gallery\\/wbAiHpwSx6AQ1W3ymu2snNTXxhuMSS44jog1qOAi.jpg\",\"products\\/gallery\\/i4yffmEEVpuY98jUO0nl5nLcoLipgfbAb4F5BOrf.jpg\",\"products\\/gallery\\/jVmrM1hMsrP4yzkeWNfS4YNr6F2kkYCSZOvQ5AEN.jpg\",\"products\\/gallery\\/OKxYZe96aDVdp9wB9pm8xFG49rarwoGeP2Rab7gU.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(74, '2026-03-09 12:07:31', '2026-04-14 16:11:45', 6, 'مشبك البراغي', 'Bolt Clamp', 'pipe-clamps-hangers-fixings-bolt-clamp', 'مشبك براغي فولاذي مزدوج التثبيت، يستخدم لربط وتجميع الأنابيب مع بعضها أو تثبيتها على الأسطح. يتميز بقوة شد عالية وسهولة في الفك والتركيب مع مقاومة ممتازة للاهتزاز.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Dual-fastening steel bolt clamp used for connecting pipes together or securing them to surfaces. Features high tightening strength, easy assembly and disassembly, and excellent vibration resistance.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 1974.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/SuSOpdr8hEpvqNfTsswPkO5RWQ5vnb61elT3LIbS.jpg', '[\"products\\/gallery\\/SSpct2pVJZTX557WIcsGPtooSuBR2Vi4673zammX.jpg\",\"products\\/gallery\\/PvMUjVP4dl9tUBBlZxZJpOEX5O1FXBZutQVXUVQA.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(75, '2026-03-09 12:07:31', '2026-04-14 16:14:02', 7, 'لوح الجبس', 'Gypsum Board', 'gypsum-partitions-suspended-ceilings-gypsum-board', 'لوح جبس عالي الجودة، مثالي للأسقف والجدران الداخلية، يتميز بمقاومة جيدة للحريق وعزل صوتي ممتاز. سطح أملس وسهل التشطيب والتركيب.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'High-quality gypsum board, ideal for ceilings and interior walls, featuring good fire resistance and excellent sound insulation. Smooth surface for easy finishing and installation.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 1519.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/XMAu2w2yu25U5bS1JpuPQHaQ2Z2yKbK6oylbPV5w.jpg', '[\"products\\/gallery\\/jR6fE3CfSav6dFQ5NDOc5N6LsZ6p2cjFaNzCxP1r.jpg\",\"products\\/gallery\\/eKvtbOOV5AFocZOtzCicooqsFQKF1T0H8XVbeIBp.jpg\",\"products\\/gallery\\/GqEOtwxQtXy2s5LvvGo0P8WnzJzt304AIbCDXPoZ.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(76, '2026-03-09 12:07:31', '2026-04-14 16:16:05', 7, 'لوح الأسمنت', 'Cement Board', 'gypsum-partitions-suspended-ceilings-cement-board', 'لوح أسمنتي متين مقاوم للماء والحريق، مثالي للاستخدام في الحمامات والمطابخ والواجهات الخارجية. يوفر قاعدة صلبة لتركيب السيراميك والرخام مع ثبات عالي ضد الرطوبة.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Durable cement board resistant to water and fire, ideal for use in bathrooms, kitchens, and exterior facades. Provides a solid base for installing ceramic tiles and marble with high stability against moisture.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 669.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/n9sjZ1ZO4rGTZSKn4MFU43lsO5gPxlFMtPL54TqN.jpg', '[\"products\\/gallery\\/t5XJEu5vs1YKwBWOWQbQCnIEeduDiAr4tnx8mtSV.jpg\",\"products\\/gallery\\/kFPGCJNSfJaSLtJdsGA0e74rNqwCPOOLGMa34NZH.jpg\",\"products\\/gallery\\/22L4yOMHTEetMUOtVVqezbIAiro8sqEdyC4Wkq7u.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(77, '2026-03-09 12:07:31', '2026-04-14 16:17:43', 7, 'معجون الجبس', 'Gypsum Putty', 'gypsum-partitions-suspended-ceilings-gypsum-putty', 'معجون جبس ناعم وسهل التشطيب، يستخدم لسد الفواصل وتغطية رؤوس البراغي وتنعيم أسطح ألواح الجبس. يتميز بقوة التصاق عالية وجفاف سريع يمنحك سطحاً أملساً جاهزاً للدهان.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Smooth, easy-to-finish gypsum putty used for sealing joints, covering screw heads, and smoothing gypsum board surfaces. Features high adhesion and fast drying, giving you a smooth surface ready for painting.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 1740.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/mj2PGUSZ3C49aufZQ0oOl4eZJSUqdeyRfQTx5zqd.jpg', '[\"products\\/gallery\\/VUGmYMHvG08lGKHRLQkdJZd43Qi6uDLohYl6tCoV.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(78, '2026-03-09 12:07:31', '2026-04-14 16:20:25', 7, 'الدعامات والقواعد', 'Stud & Runner', 'gypsum-partitions-suspended-ceilings-stud-runner', 'دعامات وقواعد معدنية مجلفنة عالية الجودة، تشكل الهيكل الأساسي لقواطع الجبس والأسقف المعلقة. تتميز بالدقة في الأبعاد وسهولة التركيب، وتمنحك نظاماً قوياً ومستقيماً طويل الأمد.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'High-quality galvanized metal studs and runners forming the essential framework for gypsum partitions and suspended ceilings. Feature precise dimensions and easy installation, providing a strong, straight, long-lasting system.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 3614.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/y7LVN8nPZE4EGD8Zs4t4CuCujVGOE8ZDkbYXhK3T.png', '[\"products\\/gallery\\/PYruCyoB8z5HZ50bFhtqTl6oPqCDDGw8AQbCXPXh.jpg\",\"products\\/gallery\\/kO9wD5S5B0PTXc0qFUDrNA1yoDOpXBsCf2MgMDJx.jpg\",\"products\\/gallery\\/T8LFS5BlP4nGJ9xd7mSLFWdEiwGLlQTDQcoqHcw0.jpg\",\"products\\/gallery\\/eNsMZ62e6THHrABaDXPEJyISGEtN7kSh43iGMuyE.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(79, '2026-03-09 12:07:31', '2026-04-14 16:23:14', 7, 'شريط شبك فاصل جدار جبس', 'Joint Mesh Tape', 'gypsum-partitions-suspended-ceilings-joint-tape', 'شريط شبكي من الألياف الزجاجية ذاتية اللصق، يستخدم لتقوية فواصل ألواح الجبس ومنع التشققات. يتميز بمرونة عالية ومقاومة للقلويات، ويمنحك وصلات متينة وسطحاً أملس طويل الأمد.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Self-adhesive fiberglass mesh tape used for reinforcing gypsum board joints and preventing cracks. Features high flexibility and alkali resistance, giving you durable joints and a long-lasting smooth surface.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 4850.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/zAiSXf00KE1y1doMPpPJsbGJPWL0b2dtG2tszuri.jpg', '[\"products\\/gallery\\/LTGN2Im7gBb8J6UwGuvlJkjXYSOoX9X4GMij8sNy.jpg\",\"products\\/gallery\\/50VCgmUYz3sAOGoyzTIvF208RSgQij5pNAmiIy2N.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(80, '2026-03-09 12:07:31', '2026-04-14 16:25:35', 7, 'زاوية جدران جبسية', 'Wall Angle', 'gypsum-partitions-suspended-ceilings-wall-angle', 'زاوية جدار جبسي معدنية مجلفنة، تستخدم لتثبيت وتوجيه ألواح الجبس عند التقاء الجدران مع الأسقف. تتميز بالدقة والصلابة، وتمنحك زوايا مستقيمة وحواف نظيفة واحترافية عالية.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Galvanized metal gypsum wall angle used for fixing and directing gypsum boards where walls meet ceilings. Features precision and rigidity, giving you straight corners, clean edges, and high professionalism.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 1708.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/H5mo38JKTJXBN38jYRNJHYiHLBCUpGz1cV4Y4puU.jpg', '[\"products\\/gallery\\/Vf73ux3w6h6NlQpR50baGyyVdzVNrQ52jze5KWnk.jpg\",\"products\\/gallery\\/KgiiHOtItrVkCNRH2XEjsoNp40lAWOW4aOWbneTd.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(81, '2026-03-09 12:07:31', '2026-04-14 16:28:15', 7, 'قناة تثبيت اسقف مستعارة (اوميغا)(الفورينج)', 'Furring Channel ) Omega)', 'gypsum-partitions-suspended-ceilings-furring-channel', 'قناة أوميغا (الفورينج) المعدنية المجلفنة، تستخدم كقاعدة رئيسية لتثبيت ألواح الجبس في الأسقف المستعارة والجدران. تصميمها على شكل حرف Omega يمنحك صلابة عالية وسهولة في التسوية والتركيب.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Galvanized metal Omega (furring) channel used as a main framework for fixing gypsum boards in suspended ceilings and walls. Its Omega-shaped design provides high rigidity and easy leveling and installation.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 772.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/ithArfGU0tX902GnbUcv1JpkJkwUtwCq6i6ptlJd.jpg', '[\"products\\/gallery\\/s2CJ9jCe6Q7emmUqDn8e6hsIsAHIFnXlH6sP43e2.jpg\",\"products\\/gallery\\/OQ2vtwpVGqMBKJfsZ269y1ffYY7NVKtKEw5GWs5T.jpg\",\"products\\/gallery\\/hLkuGq2OiJph6B29zEIaW8cC9cBYAvu5UrThBNyj.jpg\",\"products\\/gallery\\/IaPNOuVqe87h4b5Q8DYH1y96phsNY80yfwydKYEu.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(85, '2026-03-09 12:07:31', '2026-04-14 16:33:51', 7, 'مشبك زنبركي قابل للتعديل لتثبيت الأسقف المستعارة', 'Adjustable Spring Clip', 'gypsum-partitions-suspended-ceilings-adjustable-spring-clip', 'مشبك زنبركي قابل للتعديل مصمم لتعليق وتثبيت الأسقف المستعارة بمرونة عالية. يمتص الاهتزازات ويسمح بتسوية الأسقف بسهولة مع توزيع مثالي للأحمال دون الحاجة إلى لحام أو أدوات معقدة.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Adjustable spring clip designed for hanging and fixing suspended ceilings with high flexibility. Absorbs vibrations and allows easy ceiling leveling with ideal load distribution without welding or complex tools.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 861.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/qok5XymUifGVOHKUVJ1Jd2yTileDWxehUqmSRwTR.jpg', '[\"products\\/gallery\\/xJ1Ynvh3UFvLfnyII5ZZbzB47NGxmISmkYu8rCyJ.jpg\",\"products\\/gallery\\/T2RgfihIQBnWbCGGSXc8F5XjUwFBYOEIHjGonNAz.jpg\",\"products\\/gallery\\/0sDRvnpWmsUqvNoNxwt88rP0nBZxsSw0b1KxTypz.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(88, '2026-03-09 12:07:32', '2026-04-18 22:09:07', 6, 'خابور حديد سقفي', 'Drop-in Anchor', 'gypsum-partitions-suspended-ceilings-drop-in-anchor', 'خابور حديد سقفي عالي التحمل، يُستخدم لتثبيت الأحمال الثقيلة في الأسقف الخرسانية بدقة وأمان. يوفر قفلًا داخليًا موثوقًا ومقاومة ممتازة للاهتزاز.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Heavy-duty steel drop-in anchor used for securely fastening heavy loads to concrete ceilings with precision and safety. Provides reliable internal locking and excellent vibration resistance.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 182.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/t9vU8BhD0pPKc8WJ8mdVuC4bZee62U5GfLeBjvaC.jpg', '[]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(89, '2026-03-09 12:07:32', '2026-04-14 14:35:18', 7, 'شريط ورقي لوصلات الجبس', 'Gypsum Joint paper tape', 'gypsum-partitions-suspended-ceilings-g-corner-tape-corner-tape', 'شريط ورقي مثقوب عالي الجودة لتقوية وصلات ألواح الجبس ومنع التشققات. يتميز بمسامية ممتازة لالتصاق المعجون ويمنحك سطحاً أملس ومتيناً.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'High-quality perforated paper tape for reinforcing gypsum board joints and preventing cracks. Features excellent porosity for compound adhesion, giving you a smooth and durable surface.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 3345.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/svu42Jgnq7L1GWSV8J84qUReOLbWPYN6lki9jkoQ.jpg', '[\"products\\/gallery\\/C29bPwxADitLiJgsqArWAJii55JdSjdpeUUqd1SG.jpg\",\"products\\/gallery\\/0HLS0fwXaR2wnsz5lO2HGd1iZ0yxrprYdzL9ghlt.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(90, '2026-03-09 12:07:32', '2026-04-14 14:57:38', 8, 'حوامل جدارية لزوايا الانظمة الميكانيكية', 'Unistrut Channel', 'cladding-facade-accessories-unistrut-channel', 'حوامل جدارية فولاذية متعددة الاستخدامات لتركيب وتثبيت زوايا الأنظمة الميكانيكية والكهربائية والسباكة. تصميم مرن عالي التحمل يسمح بتعديل الزوايا بسهولة وأمان.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Versatile steel wall brackets for installing and securing mechanical, electrical, and plumbing system angles. A flexible, heavy-duty design allows for easy and safe angle adjustments.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 2314.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/McwIS1ikfKuB1qZyXhmbqsV1H5McHTQzT7jHR6gb.jpg', '[\"products\\/gallery\\/Yqf7aOGjTX0bt170JtPaLaBmeQprXXgrA7bpSfiR.jpg\",\"products\\/gallery\\/OnexpVlLWIysC9AJtUqF5miUbCOW6eic91IGG59Z.jpg\",\"products\\/gallery\\/x8bpbui60fU0DsAIqEzrZ6jSiRAF9i92SzAw8p6Z.jpg\",\"products\\/gallery\\/HpTUPm9LBsAEGOHRLomxdzjEJTISDeVlC7BZbQHo.png\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(91, '2026-03-09 12:07:32', '2026-04-14 15:00:22', 6, 'سيخ مسنن شرار', 'Threaded Rod', 'threaded-rod', 'سيخ مسنن عالي الجودة من الصلب، مثالي للتطبيقات الإنشائية وتعليق الأنظمة الميكانيكية والكهربائية. قوة شد ممتازة وسهولة في القص والتركيب مع الصواميل.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'High-quality steel threaded rod, ideal for structural applications and hanging mechanical and electrical systems. Excellent tensile strength and easy cutting and installation with nuts.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 3467.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/5Kl25EreHsFpBRMvCxADzB0Vew63h6nh57aLNysd.jpg', '[\"products\\/gallery\\/XsDZIrZVywhnkwNEbluCoy3K2qtQxdsDmVFQbHBR.jpg\",\"products\\/gallery\\/gwxrcuQIPUmvxM9G1VEMbq46dVxRMSR1kcdaVB5d.jpg\",\"products\\/gallery\\/smx8d7BOjnODFDUnfFvH5fXzDRCM92uKk09MEp31.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(92, '2026-03-09 12:07:32', '2026-04-14 15:02:29', 8, 'صامولة زنبركية', 'Spring Nut', 'cladding-facade-accessories-spring-nut', 'صامولة زنبركية فولاذية سهلة التركيب تنزلق بسلاسة داخل القنوات المعدنية لتثبيت البراغي والمعدات. تصميم مرن يمنحك قفلًا آمنًا ومقاومًا للاهتزاز دون الحاجة لحام.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Steel spring nut that slides smoothly inside metal channels for securing bolts and equipment. A flexible design provides a secure, vibration-resistant lock without the need for welding.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 2382.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/6o0KnULWPVrZ47Oq6i2jsqRihSkzodbyX54fVAgD.jpg', '[\"products\\/gallery\\/KHZgqi5DV7y7A6HsHlkVbOSp59pvdaUBn3LOJQiy.jpg\",\"products\\/gallery\\/H8a5xDSkjp9FPNpOj851J5kCKzxduguOjEC0nSu3.jpg\",\"products\\/gallery\\/LgQTn7soJ9dZ1RmDVHLSQxjxtvJVKCkDPWcMPPi1.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(93, '2026-03-09 12:07:32', '2026-04-14 15:07:22', 8, 'زوايا تثبيت رخام ميكانيكية', 'Mechanical Bracket', 'cladding-facade-accessories-bracket', 'زوايا معدنية ميكانيكية مصممة خصيصًا لتثبيت الرخام والحجر على الواجهات والجدران دون الحاجة إلى مواد لاصقة. تمنحك توزيعًا مثاليًا للأحمال وثباتًا طويل الأمد.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Metal mechanical brackets specifically designed for installing marble and stone on facades and walls without the need for adhesives. Provide ideal load distribution and long-lasting stability.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 987.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/ShUU3kREd57T37ANZZhwf7ISu71SE8z1yj0fqoWI.jpg', '[\"products\\/gallery\\/EKEsyJNkuvOoCB0DgmZAoTQQ8wu2504hC6NRoqm8.jpg\",\"products\\/gallery\\/odUFHVLeXnJtwsuBJmCtNLpJrV2VpuVyUKxetlq3.jpg\",\"products\\/gallery\\/yfPOrq0GGQVjL9Qp3MgY9xzFIDEJDhfIplNcsII6.jpg\",\"products\\/gallery\\/cDdct3e0Wrxm2y1Wuh5Gy2A8ht95nLSG9lxk9XJl.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(94, '2026-03-09 12:07:32', '2026-04-11 00:18:53', 8, 'خابور تثبيت', 'Flat Anchor', 'cladding-facade-accessories-flat-anchor', 'منتج عالي الجودة من إكسسوارات الكلادينج والواجهات | Cladding & Facade Accessories', NULL, NULL, NULL, NULL, 211.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/ClEHnQxmaAJAE3KrAd4iJqX5qVyRQTPmsvNFO8l0.jpg', '[]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(95, '2026-03-09 12:07:32', '2026-04-14 15:13:55', 6, 'برغي برأس سداسي', 'Hex Head Bolt', 'hex-head-bolt', 'برغي برأس سداسي عالي القوة مصنوع من الصلب، مثالي للتوصيلات الإنشائية والميكانيكية الثقيلة. يوفر إحكامًا ممتازًا ومقاومة عالية للقص والشد مع سهولة الربط بالمفتاح.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'High-strength hex head bolt made of steel, ideal for heavy structural and mechanical connections. Provides excellent tightening and high shear and tensile resistance with easy wrench fastening.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 3302.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/Ck2Glf1tuIRG9gLsVO7owTq47QMvE7TMwSi0oMk2.jpg', '[\"products\\/gallery\\/L4DeygZEnyVBQaIAqRjv0wZR2qhXw8IVaWDM2Usa.jpg\",\"products\\/gallery\\/v9g1H7w87x94Ba0TA8bzYYcYBaaVm2GG1MKbgi63.jpg\",\"products\\/gallery\\/I7QmJOpAFEVdQ1VrNpXIKsdk8DGcPJi1QyvuEK8a.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(96, '2026-03-09 12:07:32', '2026-04-14 15:16:54', 6, 'برغي التثبيت', 'Through Bolt', 'cladding-facade-accessories-through-bolt', 'برغي تثبيت كامل العبور مصنوع من الصلب المجلفن، يخترق الخرسانة بالكامل لتوزيع الأحمال بشكل متساوٍ وآمن. مثالي لتثبيت الهياكل الثقيلة والمعدات الضخمة على الأرضيات والجدران.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Full-length through bolt made of galvanized steel, completely penetrating concrete for even and secure load distribution. Ideal for anchoring heavy structures and massive equipment to floors and walls.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 2110.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/9NRaC5KzbBWJYXkBmHsPiLkojXnNSgLQCE00bhtT.jpg', '[\"products\\/gallery\\/pcxbUbED7QTiZkcevWLh8vZFJy5XYd3GtlA4zdmK.jpg\",\"products\\/gallery\\/taLJRQ03MNT9eTRaAyLsndm46eXresZXmCwGO7l2.jpg\",\"products\\/gallery\\/9eKyCTLtDigwhMvcXzceCcE2WTV6Gd4rSEKPDJ7B.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(97, '2026-03-09 12:07:32', '2026-04-14 15:21:40', 9, 'الأدوات اليدوية', 'Hand Tools', 'consumable-items-hardware-hand-tools', 'مجموعة متكاملة من الأدوات اليدوية عالية الجودة والمتانة، تشمل مفكات، شدادات، مفاتيح ربط، وعدة قياس. صممت خصيصًا لتناسب أعمال التركيب والصيانة في مشاريع البناء.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'A comprehensive range of high-quality, durable hand tools including screwdrivers, wrenches, pliers, and measuring tools. Specifically designed for installation and maintenance work in construction projects.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 2158.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/fZKfRIYQoYwF8fWSuUR6ArjQmzIY3gR8CaTsfKZj.jpg', '[\"products\\/gallery\\/rs08gQkdooNNDp8rLnt3P43WeD6roTPVpfvOXRAM.jpg\",\"products\\/gallery\\/AgeQdlS9v108eluSOOeOamApeiKUneEQgg87ppOz.jpg\",\"products\\/gallery\\/lBETnozd6TdFNxpKM9rE3srNNIm0WNHuXs5yjoIT.jpg\",\"products\\/gallery\\/QX9qEA9MWVyxMsEuDuWXWmIUHrD2HE9OJdHlghy5.jpg\",\"products\\/gallery\\/uhdg3xsugmwVSpC6Vdin90zKJHmdP3jwbl2N02II.jpg\",\"products\\/gallery\\/7msNPNCDosvpxHUPA6eRHPItCmuusDW8tzGisPjC.jpg\",\"products\\/gallery\\/ssBqgwhcqd9UlCnMhPbkEAiZEPOkYlb5Zv0Z56Tc.jpg\",\"products\\/gallery\\/71DgxmRb5AO3MFEigCrrSTsrvoCMQBs8CiNT3vBy.jpg\",\"products\\/gallery\\/mPhY98xO0cStRsrCuD842rUlj89hjcDjhI3ywltL.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(98, '2026-03-09 12:07:32', '2026-04-14 15:24:09', 9, 'مسمار خشب', 'Common Nails', 'consumable-items-hardware-common-nails', 'مسمار خشب عادي متعدد الاستخدامات مصنوع من الصلب المتين، مثالي لأعمال النجارة والتشطيب وتركيب الأخشاب. يوفر تثبيتًا قويًا وسهولة في الدق مع مقاومة جيدة للانحناء.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Versatile common wood nails made of durable steel, ideal for carpentry, finishing, and timber installation. Provides strong fastening and easy driving with good resistance to bending.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 973.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/snd263odMAnSdwKxpuAtEXn8IbBj5Jxyf7MuQscq.jpg', '[\"products\\/gallery\\/ZMuJluqnap1w1cId5mNPzfFecRaap5vTLnz7vOc2.jpg\",\"products\\/gallery\\/1Z4nW9bXFZqkggpSRRAphuOaO8SBQskaAEs7j9qz.jpg\",\"products\\/gallery\\/fl3TpzCTdFrMMJdaIPRtpU6GAsKeDOfFHWG88FKA.jpg\",\"products\\/gallery\\/0P6zOWRQrFxvjT5mzgInYo2NbX93DqAAB1hU8hX7.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(99, '2026-03-09 12:07:32', '2026-04-14 15:26:58', 9, 'مسامير الخرسانة', 'Concrete Nails', 'consumable-items-hardware-concrete-nails', 'مسامير خرسانة مصنوعة من صلب عالي الصلابة ومشحونة حرارياً لاختراق الخرسانة والطوب بسهولة. توفر تثبيتاً قوياً وسريعاً للأخشاب والمعادن على الأسطح الصلبة دون حاجة لحفر مسبق.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Concrete nails made of high-hardness steel and heat-treated for easy penetration into concrete and brick. Provide strong and fast fastening of wood and metal to hard surfaces without pre-drilling.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 4458.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/druht4moHfkV7BkULqXV2EjfwQzBF87ydqh4UI72.jpg', '[\"products\\/gallery\\/Z7ESiB2lxMcMO9zoEjrLYN4gnDeL1QH7kOjJV6i7.jpg\",\"products\\/gallery\\/UT9TpZSBA2IY64eKlLEmyLkMTcQR9PgmaaX7lbOn.jpg\",\"products\\/gallery\\/f7qzT3mqxk19oidMfa08IPziit2wKoNvP8gqlZ7O.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(100, '2026-03-09 12:07:32', '2026-04-14 15:42:57', 9, 'الخيش', 'Burlap', 'consumable-items-hardware-burlap-hessian-cloth', 'خيش بناء عالي الجودة مصنوع من ألياف طبيعية متينة، يستخدم لحماية الخرسانة الطازجة أثناء المعالجة ولتغطية الأسطح ومنع التشققات. يسمح بمرور الرطوبة مع الاحتفاظ بالحرارة اللازمة للتصلب الأمثل.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'High-quality construction burlap made of durable natural fibers, used for curing fresh concrete and covering surfaces to prevent cracks. Allows moisture passage while retaining necessary heat for optimal hardening.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 4175.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/Py21u8FeDoPQCE1BdYhw2wixHACfHVbApFQ6VX1I.jpg', '[]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(101, '2026-03-09 12:07:32', '2026-04-14 15:48:53', 9, 'شريط تربيط حديدتسليح', 'Binding Iron Wire', 'consumable-items-hardware-tie-wire', 'شريط تربيط حديدي مصنوع من صلب مرن وعالي الجودة، يستخدم لربط وتثبيت قضبان حديد التسليح في الأعمال الخرسانية. يضمن شدًا آمنًا ومقاومًا للصدأ مع سهولة في اللف والقطع.\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'Flexible, high-quality iron binding wire used for tying and securing reinforcing steel bars in concrete works. Ensures secure, rust-resistant tightening with easy wrapping and cutting.\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, 1985.00, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/w2Z7OB4zgC4PFsHjT42jf548xecgbHOktojXsGfd.jpg', '[\"products\\/gallery\\/ReRL1YH5Tzh7iiiJSZjl0I2kO2mtj2BKPruU7p02.jpg\",\"products\\/gallery\\/0FkMRZnl1Fo4Godx1XGd8ihxUFpgpESVTG9Ieydw.jpg\",\"products\\/gallery\\/6R5tJKtFoJcifWz7aK8O8K8MhpBkaYA8LiGB2yN2.jpg\",\"products\\/gallery\\/wLfRIiTcKPpQo4CrKVNlILWPlXHBbbDATu1O0J9S.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(103, '2026-04-25 14:28:20', '2026-04-25 14:28:20', 11, 'مضخات مياه', 'WATER PUMP', 'w', 'مضخة مياه عالية الأداء والتحمل، تستخدم لرفع ونقل المياه في المشاريع السكنية والتجارية والزراعية والصناعية. تتميز بقوة شفط ممتازة، استهلاك منخفض للطاقة، هيكل مقاوم للصدأ والتآكل، وسهولة في التركيب والصيانة.\r\n\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'High-performance, durable water pump used for lifting and transferring water in residential, commercial, agricultural, and industrial projects. Features excellent suction power, low energy consumption, a rust and corrosion-resistant body, and easy installation and maintenance.\r\n\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/i70npYMZOV5JemVrUXLIsHAn2ihyooj8fFhkC64Z.jpg', '[\"products\\/gallery\\/nJiVgAwedT7l8q62DFFp2ZzCsGJJYcCPA7An7EXZ.jpg\",\"products\\/gallery\\/rGdgPRum8OudNEBDcg2uEqjVoJtF4dhJmeeLFDTg.jpg\",\"products\\/gallery\\/wOMcWGkapzHxRUsBEwPdGAWdfquY5OvKrdgHYZQ9.jpg\",\"products\\/gallery\\/TWRifAc9sieS90uLdw9ok0CSBoiU1r03jN0sAHFc.jpg\",\"products\\/gallery\\/ebriU6qTpUrLaUWJHRInkEalU6mfZ5FHNaI87PBc.jpg\",\"products\\/gallery\\/uVFfK8Rs3OlAmGvVPVJJuu4Ok4zvfRzePXNgaEij.jpg\",\"products\\/gallery\\/kuQzc4k87Pibaw6GjvlYOkXD5D4i9K2EtW33CW4L.jpg\",\"products\\/gallery\\/5nvWSHFPyhNrmKqFdq8AcHFl3zEl2nUpVijsZbC5.jpg\",\"products\\/gallery\\/ljS4fZ50s8tq6se6VaDgOc3p5r9DJoL0J2Xjrzci.jpg\",\"products\\/gallery\\/6gXFlOiKqnDebEGAMKbsF6aZfRzcNU6yJNC7Nu4p.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(104, '2026-04-25 14:32:20', '2026-04-25 14:32:20', 11, 'خلاط مياه ( سواد)', 'WATER MIXER', 'm', 'خلاط مياه عالي الجودة والتصميم، يستخدم للتحكم الدقيق بمياه السخان والبارد في الحمامات والمطابخ. يتميز بجسم مصنوع من النحاس المقاوم للصدأ والتسرب، يد حركة سلسة، وأداء يدوم طويلاً مع تركيب احترافي سهل.\r\n\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'High-quality water mixer, used for precise control of hot and cold water in bathrooms and kitchens. Features a brass body resistant to rust and leakage, smooth handle movement, and long-lasting performance with easy professional installation.\r\n\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/u7ozTiKhAVgyU7CLF6ncqAocNBs2AXebdLX1drAN.jpg', '[\"products\\/gallery\\/rHaAMudDuS2RJ5GB5rofPCqJJMAzWCAnqsuxA1HO.jpg\",\"products\\/gallery\\/Zl4bMsQQU9HluxxuTYijVwev6YZgg83uSdUB7mGa.jpg\",\"products\\/gallery\\/H6F5vHmQNwXP6UVpR4C0egp1fSLnK8jOQzA4wKEO.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1);
INSERT INTO `products` (`id`, `created_at`, `updated_at`, `category_id`, `name_ar`, `name_en`, `slug`, `description_ar`, `description_en`, `short_description_ar`, `short_description_en`, `seo`, `price`, `cost_price`, `tax_rate`, `taxable`, `unit`, `stock_quantity`, `min_stock`, `reorder_point`, `show_price`, `image_main`, `image_gallery`, `brand`, `model`, `sku`, `barcode`, `color`, `size`, `variant_group`, `in_stock`, `is_featured`, `views_count`, `sort_order`, `max_stock`, `weight`, `length`, `width`, `height`, `is_active`) VALUES
(105, '2026-04-25 14:34:10', '2026-04-25 14:34:10', 11, 'خلاط دوش', 'BATH MIXER', 'b', 'خلاط دوش (شاور) عالي الجودة من النحاس المقاوم للصدأ والتسرب، يستخدم للتحكم الدقيق بمياه السخان والبارد مع إمكانية التحويل بين الدش والحنفية. يتميز بتصميم عصري، يد سلسة الحركة، وأداء يدوم طويلاً.\r\n\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'High-quality bath (shower) mixer made of brass, resistant to rust and leakage, used for precise control of hot and cold water with the ability to switch between shower and spout. Features a modern design, smooth handle movement, and long-lasting performance.\r\n\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/TvjHDK4GPHZYda8h3mWbjDyhhQAWZWVAXLtwgA4E.jpg', '[\"products\\/gallery\\/szczUD20R2qVyrEV75jTpARFaVNXaRiUIsYTtfhI.jpg\",\"products\\/gallery\\/Zqb7db033PYspuyrGOOjKtdDajyFpB3aShd1Ni0G.jpg\",\"products\\/gallery\\/c57jFkdLmo3ULKW0qKnFcUFCSJGvfXlSoIBzypT8.jpg\",\"products\\/gallery\\/vDLK6WSNE6WFwJMiBYs30vsehCKuFy3c83LNsR70.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(106, '2026-04-25 14:39:15', '2026-04-25 14:39:15', 11, 'أكسسوارات مواد صحية', 'SANITARY ACCESSORIES', 's', 'مجموعة متكاملة من الأكسسوارات الصحية عالية الجودة، تشمل شطافات، تيپ تيفلون، بربيش توصيل مغاسل، خلاطات، وحنفيات نحاس. تتميز بمقاومة ممتازة للصدأ والتسرب، سهلة التركيب، وتناسب جميع المشاريع السكنية والتجارية.\r\n\r\nلطلب الكتالوج الخاص بالمنتج، نرجو التواصل على أرقام الاتصال الموجودة بالموقع.', 'A comprehensive range of high-quality sanitary accessories including bidet showers, Teflon tape, sink connection hoses, mixers, and brass faucets. Feature excellent resistance to rust and leakage, easy to install, suitable for all residential and commercial projects.\r\n\r\nTo request the product catalog, please contact us via the phone numbers listed on the website.', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 0, NULL, 1, 'products/eQoJp0GynI0hvmGipyjDdiMk5pL3uYcGzUxujnuU.jpg', '[\"products\\/gallery\\/NwzIyUJJBjeHA2GrsBHOzDQlZFyHIu95p6Yz55dU.jpg\",\"products\\/gallery\\/IlZyewiysjuICFlIS7qp6W1CFQYoTFTeEPsUQnlt.jpg\",\"products\\/gallery\\/k8dzjfHDSkFoOYipIcPxzecWkAeVFXNwydTUfuOC.jpg\",\"products\\/gallery\\/x4VlhaJ29wA7TwJn9PL0dvlRqdHQ4Aejva0C3X0T.jpg\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1);

-- --------------------------------------------------------

--
-- بنية الجدول `product_batches`
--

CREATE TABLE `product_batches` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `product_variant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `warehouse_id` bigint(20) UNSIGNED NOT NULL,
  `batch_number` varchar(255) NOT NULL,
  `manufacturing_date` date DEFAULT NULL,
  `expiry_date` date NOT NULL,
  `quantity` int(11) NOT NULL,
  `quantity_reserved` int(11) NOT NULL DEFAULT 0,
  `quantity_available` int(11) GENERATED ALWAYS AS (`quantity` - `quantity_reserved`) VIRTUAL,
  `unit_cost` decimal(10,2) NOT NULL,
  `status` enum('available','reserved','expired','quarantined') NOT NULL DEFAULT 'available',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `product_serial_numbers`
--

CREATE TABLE `product_serial_numbers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `product_variant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `warehouse_id` bigint(20) UNSIGNED NOT NULL,
  `batch_id` bigint(20) UNSIGNED DEFAULT NULL,
  `serial_number` varchar(255) NOT NULL,
  `status` enum('in_stock','reserved','sold','damaged','lost','quarantined') NOT NULL DEFAULT 'in_stock',
  `sale_order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sale_order_item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sold_at` timestamp NULL DEFAULT NULL,
  `reserved_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `product_units`
--

CREATE TABLE `product_units` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `name_ar` varchar(255) DEFAULT NULL,
  `barcode` varchar(255) DEFAULT NULL,
  `base_unit_multiplier` int(11) NOT NULL DEFAULT 1,
  `price_multiplier` decimal(8,4) NOT NULL DEFAULT 1.0000,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `product_variants`
--

CREATE TABLE `product_variants` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `sku` varchar(255) NOT NULL,
  `barcode` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `color` varchar(255) DEFAULT NULL,
  `size` varchar(255) DEFAULT NULL,
  `material` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `supplier_id` bigint(20) UNSIGNED NOT NULL,
  `order_number` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `due_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(255) NOT NULL DEFAULT 'SAR',
  `order_date` date DEFAULT NULL,
  `received_date` date DEFAULT NULL,
  `paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `due_amount` decimal(12,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `purchase_order_items`
--

CREATE TABLE `purchase_order_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `purchase_order_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `purchase_receipts`
--

CREATE TABLE `purchase_receipts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `receipt_number` varchar(255) NOT NULL,
  `purchase_order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `supplier_id` bigint(20) UNSIGNED DEFAULT NULL,
  `receipt_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'received',
  `currency` varchar(255) NOT NULL DEFAULT 'SAR',
  `shipping_address` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `purchase_receipt_items`
--

CREATE TABLE `purchase_receipt_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `purchase_receipt_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `quotes`
--

CREATE TABLE `quotes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `quote_number` varchar(255) NOT NULL,
  `customer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('draft','sent','accepted','rejected','expired') NOT NULL DEFAULT 'draft',
  `valid_until` date DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `terms` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `quote_items`
--

CREATE TABLE `quote_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `quote_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `reorder_alerts`
--

CREATE TABLE `reorder_alerts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `warehouse_id` bigint(20) UNSIGNED NOT NULL,
  `product_variant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `current_quantity` int(11) NOT NULL,
  `reorder_point` int(11) NOT NULL,
  `safety_stock` int(11) NOT NULL,
  `suggested_order_quantity` int(11) NOT NULL,
  `severity` enum('low','medium','critical') NOT NULL DEFAULT 'medium',
  `status` enum('pending','ordered','resolved','dismissed') NOT NULL DEFAULT 'pending',
  `alerted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `reports`
--

CREATE TABLE `reports` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `name_ar` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `type` enum('sales','inventory','warehouse','financial','customer','custom') NOT NULL,
  `format` enum('table','chart','pivot','summary') NOT NULL,
  `query_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`query_config`)),
  `filter_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`filter_config`)),
  `column_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`column_config`)),
  `chart_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`chart_config`)),
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 0,
  `is_scheduled` tinyint(1) NOT NULL DEFAULT 0,
  `schedule_frequency` varchar(255) DEFAULT NULL,
  `schedule_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`schedule_config`)),
  `last_run_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `reviews`
--

CREATE TABLE `reviews` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `rating` int(11) NOT NULL,
  `comment` text DEFAULT NULL,
  `images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`images`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `rma_items`
--

CREATE TABLE `rma_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `rma_request_id` bigint(20) UNSIGNED NOT NULL,
  `sales_order_item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `product_id` bigint(20) UNSIGNED DEFAULT NULL,
  `product_variant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `quantity_requested` int(11) NOT NULL,
  `quantity_received` int(11) NOT NULL DEFAULT 0,
  `condition` enum('new','used','damaged','missing') NOT NULL DEFAULT 'new',
  `resolution` enum('refund','exchange','repair','discard') DEFAULT NULL,
  `exchange_product_id` bigint(20) UNSIGNED DEFAULT NULL,
  `exchange_variant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `refund_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `rma_requests`
--

CREATE TABLE `rma_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `rma_number` varchar(255) NOT NULL,
  `customer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sales_order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('pending','approved','rejected','completed','cancelled') NOT NULL DEFAULT 'pending',
  `type` enum('refund','exchange','store_credit') DEFAULT NULL,
  `reason` enum('defective','damaged','wrong_item','not_as_described','changed_mind','other') DEFAULT NULL,
  `reason_description` text DEFAULT NULL,
  `return_address` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`return_address`)),
  `requested_at` timestamp NULL DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `completed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `refund_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `refund_method` enum('original','store_credit','bank_transfer','check') DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `resolution_type` enum('refund','replacement','repair','store_credit','exchange') DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `roles`
--

CREATE TABLE `roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `display_name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `roles`
--

INSERT INTO `roles` (`id`, `name`, `display_name`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'مدير النظام', 'صلاحيات كاملة على النظام', 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(2, 'manager', 'مدير', 'صلاحيات إدارية محدودة', 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46'),
(3, 'employee', 'موظف', 'صلاحيات الموظفين', 1, '2026-06-26 05:11:46', '2026-06-26 05:11:46');

-- --------------------------------------------------------

--
-- بنية الجدول `role_user`
--

CREATE TABLE `role_user` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `sales_contracts`
--

CREATE TABLE `sales_contracts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `customer_id` bigint(20) UNSIGNED NOT NULL,
  `contract_number` varchar(255) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('draft','active','expired','cancelled') NOT NULL DEFAULT 'draft',
  `total_value` decimal(15,2) NOT NULL,
  `discount_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `terms` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`terms`)),
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `sales_orders`
--

CREATE TABLE `sales_orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_number` varchar(255) NOT NULL,
  `customer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `quote_id` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('pending','confirmed','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `order_date` date DEFAULT NULL,
  `expected_delivery` date DEFAULT NULL,
  `actual_delivery_date` timestamp NULL DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `coupon_code` varchar(255) DEFAULT NULL,
  `customer_notes` text DEFAULT NULL,
  `internal_notes` text DEFAULT NULL,
  `synced_at` timestamp NULL DEFAULT NULL,
  `sync_status` varchar(255) DEFAULT NULL,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `shipping_address` text DEFAULT NULL,
  `billing_address` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`billing_address`)),
  `tracking_number` varchar(255) DEFAULT NULL,
  `carrier` varchar(255) DEFAULT NULL,
  `shipping_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `currency` varchar(255) NOT NULL DEFAULT 'SAR',
  `paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `due_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `confirmed_at` date DEFAULT NULL,
  `shipped_at` date DEFAULT NULL,
  `delivered_at` date DEFAULT NULL,
  `channel_id` bigint(20) UNSIGNED DEFAULT NULL,
  `external_order_id` varchar(255) DEFAULT NULL,
  `contract_id` bigint(20) UNSIGNED DEFAULT NULL,
  `fulfillment_type` enum('ship','pickup','delivery') NOT NULL DEFAULT 'ship',
  `fulfillment_warehouse_id` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `sales_order_items`
--

CREATE TABLE `sales_order_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `sales_order_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('5MznF9QmmdMUlYHkVfRcUGCJEBCmgD4vraBdM0CM', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '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bHVlIjtzOjI0MToi2YbYrdmGINmB2Yog2KPZiNin2YYg2KfZhNiq2YLYr9mFINmG2YLYr9mFINmF2LPYqtmE2LLZhdin2Kog2KfZhNio2YbYp9ihINin2YTYqtmKINiq2KzZhdi5INio2YrZhiDYp9mE2KzZiNiv2Kkg2KfZhNi52KfZhNmF2YrYqSDZiNin2YTYudi12LHZitipINmB2Yog2KfZhNiq2LXZhdmK2YXYjCDZhNmG2YPZiNmGINi02LHZitmD2YMg2KfZhNij2YXYq9mEINmB2Yog2YXYtNin2LHZiti52YMg2KfZhNil2YbYtNin2KbZitipLiI7czo0OiJ0eXBlIjtzOjg6InRleHRhcmVhIjtzOjU6Imdyb3VwIjtzOjc6ImdlbmVyYWwiO3M6MTA6ImNyZWF0ZWRfYXQiO3M6MTk6IjIwMjYtMDMtMDkgMTU6MDc6MjciO3M6MTA6InVwZGF0ZWRfYXQiO3M6MTk6IjIwMjYtMDMtMDkgMTU6MDc6MjciO31zOjExOiIAKgBvcmlnaW5hbCI7YTo3OntzOjI6ImlkIjtpOjM7czozOiJrZXkiO3M6MTY6InNpdGVfZGVzY3JpcHRpb24iO3M6NToidmFsdWUiO3M6MjQxOiLZhtit2YYg2YHZiiDYo9mI2KfZhiDYp9mE2KrZgtiv2YUg2YbZgtiv2YUg2YXYs9iq2YTYstmF2KfYqiDYp9mE2KjZhtin2KEg2KfZhNiq2Yog2KrYrNmF2Lkg2KjZitmGINin2YTYrNmI2K/YqSDYp9mE2LnYp9mE2YXZitipINmI2KfZhNi52LXYsdmK2Kkg2YHZiiDYp9mE2KrYtdmF2YrZhdiMINmE2YbZg9mI2YYg2LTYsdmK2YPZgyDYp9mE2KPZhdir2YQg2YHZiiDZhdi02KfYsdmK2LnZgyDYp9mE2KXZhti02KfYptmK2KkuIjtzOjQ6InR5cGUiO3M6ODoidGV4dGFyZWEiO3M6NToiZ3JvdXAiO3M6NzoiZ2VuZXJhbCI7czoxMDoiY3JlYXRlZF9hdCI7czoxOToiMjAyNi0wMy0wOSAxNTowNzoyNyI7czoxMDoidXBkYXRlZF9hdCI7czoxOToiMjAyNi0wMy0wOSAxNTowNzoyNyI7fXM6MTA6IgAqAGNoYW5nZXMiO2E6MDp7fXM6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', 1777144100);
INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('jlguKBhYVkyGdSyrZGTSiLB9X6FdQrKwdMJhNuJF', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '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IjthOjc6e3M6MjoiaWQiO2k6MztzOjM6ImtleSI7czoxNjoic2l0ZV9kZXNjcmlwdGlvbiI7czo1OiJ2YWx1ZSI7czoyNDE6ItmG2K3ZhiDZgdmKINij2YjYp9mGINin2YTYqtmC2K/ZhSDZhtmC2K/ZhSDZhdiz2KrZhNiy2YXYp9iqINin2YTYqNmG2KfYoSDYp9mE2KrZiiDYqtis2YXYuSDYqNmK2YYg2KfZhNis2YjYr9ipINin2YTYudin2YTZhdmK2Kkg2YjYp9mE2LnYtdix2YrYqSDZgdmKINin2YTYqti12YXZitmF2Iwg2YTZhtmD2YjZhiDYtNix2YrZg9mDINin2YTYo9mF2KvZhCDZgdmKINmF2LTYp9ix2YrYudmDINin2YTYpdmG2LTYp9im2YrYqS4iO3M6NDoidHlwZSI7czo4OiJ0ZXh0YXJlYSI7czo1OiJncm91cCI7czo3OiJnZW5lcmFsIjtzOjEwOiJjcmVhdGVkX2F0IjtzOjE5OiIyMDI2LTAzLTA5IDE1OjA3OjI3IjtzOjEwOiJ1cGRhdGVkX2F0IjtzOjE5OiIyMDI2LTAzLTA5IDE1OjA3OjI3Ijt9czoxMToiACoAb3JpZ2luYWwiO2E6Nzp7czoyOiJpZCI7aTozO3M6Mzoia2V5IjtzOjE2OiJzaXRlX2Rlc2NyaXB0aW9uIjtzOjU6InZhbHVlIjtzOjI0MToi2YbYrdmGINmB2Yog2KPZiNin2YYg2KfZhNiq2YLYr9mFINmG2YLYr9mFINmF2LPYqtmE2LLZhdin2Kog2KfZhNio2YbYp9ihINin2YTYqtmKINiq2KzZhdi5INio2YrZhiDYp9mE2KzZiNiv2Kkg2KfZhNi52KfZhNmF2YrYqSDZiNin2YTYudi12LHZitipINmB2Yog2KfZhNiq2LXZhdmK2YXYjCDZhNmG2YPZiNmGINi02LHZitmD2YMg2KfZhNij2YXYq9mEINmB2Yog2YXYtNin2LHZiti52YMg2KfZhNil2YbYtNin2KbZitipLiI7czo0OiJ0eXBlIjtzOjg6InRleHRhcmVhIjtzOjU6Imdyb3VwIjtzOjc6ImdlbmVyYWwiO3M6MTA6ImNyZWF0ZWRfYXQiO3M6MTk6IjIwMjYtMDMtMDkgMTU6MDc6MjciO3M6MTA6InVwZGF0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', 1777208895);
INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('pvLQ1vZDX4azJw66kjjJ1OOInhY0XLUkjMEF3jd4', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '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OjA7czoxMzoiACoAYXR0cmlidXRlcyI7YTo3OntzOjI6ImlkIjtpOjM7czozOiJrZXkiO3M6MTY6InNpdGVfZGVzY3JpcHRpb24iO3M6NToidmFsdWUiO3M6MjQxOiLZhtit2YYg2YHZiiDYo9mI2KfZhiDYp9mE2KrZgtiv2YUg2YbZgtiv2YUg2YXYs9iq2YTYstmF2KfYqiDYp9mE2KjZhtin2KEg2KfZhNiq2Yog2KrYrNmF2Lkg2KjZitmGINin2YTYrNmI2K/YqSDYp9mE2LnYp9mE2YXZitipINmI2KfZhNi52LXYsdmK2Kkg2YHZiiDYp9mE2KrYtdmF2YrZhdiMINmE2YbZg9mI2YYg2LTYsdmK2YPZgyDYp9mE2KPZhdir2YQg2YHZiiDZhdi02KfYsdmK2LnZgyDYp9mE2KXZhti02KfYptmK2KkuIjtzOjQ6InR5cGUiO3M6ODoidGV4dGFyZWEiO3M6NToiZ3JvdXAiO3M6NzoiZ2VuZXJhbCI7czoxMDoiY3JlYXRlZF9hdCI7czoxOToiMjAyNi0wMy0wOSAxNTowNzoyNyI7czoxMDoidXBkYXRlZF9hdCI7czoxOToiMjAyNi0wMy0wOSAxNTowNzoyNyI7fXM6MTE6IgAqAG9yaWdpbmFsIjthOjc6e3M6MjoiaWQiO2k6MztzOjM6ImtleSI7czoxNjoic2l0ZV9kZXNjcmlwdGlvbiI7czo1OiJ2YWx1ZSI7czoyNDE6ItmG2K3ZhiDZgdmKINij2YjYp9mGINin2YTYqtmC2K/ZhSDZhtmC2K/ZhSDZhdiz2KrZhNiy2YXYp9iqINin2YTYqNmG2KfYoSDYp9mE2KrZiiDYqtis2YXYuSDYqNmK2YYg2KfZhNis2YjYr9ipINin2YTYudin2YTZhdmK2Kkg2YjYp9mE2LnYtdix2YrYqSDZgdmKINin2YTYqti12YXZitmF2Iwg2YTZhtmD2YjZhiDYtNix2YrZg9mDINin2YTYo9mF2KvZhCDZgdmKINmF2LTYp9ix2YrYudmDINin2YTYpdmG2LTYp9im2YrYqS4iO3M6NDoidHlwZSI7czo4OiJ0ZXh0YXJlYSI7czo1OiJncm91cCI7czo3OiJnZW5lcmFsIjtzOjEwOiJjcmVhdGVkX2F0IjtzOjE5OiIyMDI2LTAzLTA5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', 1782455108);

-- --------------------------------------------------------

--
-- بنية الجدول `settings`
--

CREATE TABLE `settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `key` varchar(255) NOT NULL,
  `value` text DEFAULT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'text',
  `group` varchar(255) NOT NULL DEFAULT 'general',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `settings`
--

INSERT INTO `settings` (`id`, `key`, `value`, `type`, `group`, `created_at`, `updated_at`) VALUES
(1, 'site_name', 'أوان التقدم', 'text', 'general', '2026-03-09 12:07:27', '2026-06-26 02:29:08'),
(2, 'site_tagline', 'نبني معاً غد سورية الأجمل', 'text', 'general', '2026-03-09 12:07:27', '2026-03-09 12:07:27'),
(3, 'site_description', 'نحن في أوان التقدم نقدم مستلزمات البناء التي تجمع بين الجودة العالمية والعصرية في التصميم، لنكون شريكك الأمثل في مشاريعك الإنشائية.', 'textarea', 'general', '2026-03-09 12:07:27', '2026-03-09 12:07:27'),
(4, 'site_logo', 'assets/images/logo.png', 'text', 'general', '2026-03-09 12:07:27', '2026-03-09 12:07:27'),
(5, 'show_site_name', '1', 'boolean', 'general', '2026-03-09 12:07:27', '2026-03-11 05:39:28'),
(6, 'meta_description', 'نحن في أوان التقدم نقدم مستلزمات البناء التي تجمع بين الجودة العالمية والعصرية في التصميم، لنكون شريكك الأمثل في مشاريعك الإنشائية.', 'textarea', 'seo', '2026-03-09 12:07:27', '2026-03-09 12:07:27'),
(7, 'meta_keywords', 'مواد بناء, مضخات مياه, خلاطات حمامات, أكسسوارات صحية, كلادينج, قواطع جبسية, أدوات, مشابك, علاقات معدنية, أنظمة تثبيت ورفع', 'text', 'seo', '2026-03-09 12:07:27', '2026-03-09 12:07:27'),
(8, 'og_description', 'مستلزمات البناء بأعلى جودة وتصاميم عصرية لمشاريعكم في سورية', 'text', 'seo', '2026-03-09 12:07:27', '2026-03-09 12:07:27'),
(9, 'contact_phone', '00963962889577', 'text', 'contact', '2026-03-09 12:07:27', '2026-04-18 23:10:08'),
(10, 'contact_email', 'awaanaltakadom@gmail.com', 'text', 'contact', '2026-03-09 12:07:27', '2026-04-19 16:54:13'),
(11, 'contact_whatsapp', '00963962889577', 'text', 'contact', '2026-03-09 12:07:27', '2026-04-18 23:10:08'),
(12, 'contact_facebook', 'https://www.facebook.com/share/18AcYpks2o/', 'text', 'contact', '2026-03-09 12:07:27', '2026-06-26 02:27:13'),
(13, 'contact_address', 'المملكة العربية السعوديه - الرياض\r\nالفرع2: سوريا - دمشق', 'text', 'contact', '2026-03-09 12:07:27', '2026-06-26 03:24:40'),
(14, 'address', 'المملكة العربية السعوديه - الرياض\r\nالفرع2: سوريا - دمشق', 'text', 'general', '2026-03-10 13:49:17', '2026-06-26 03:24:40'),
(15, 'working_hours', 'السبت الى الخميس 08:00-22:00', 'text', 'general', '2026-03-10 13:49:17', '2026-04-19 16:54:13'),
(16, 'facebook', 'https://www.facebook.com/share/18AcYpks2o/', 'text', 'general', '2026-03-10 13:49:17', '2026-04-18 23:10:52'),
(17, 'instagram', 'https://www.instagram.com/awaan_altakadm.co?igsh=MjQydTZmZTkweXg3', 'text', 'general', '2026-03-10 13:49:17', '2026-04-18 23:10:52'),
(18, 'twitter', 'https://x.com/awaan', 'text', 'general', '2026-03-10 13:49:17', '2026-06-26 02:27:13'),
(19, 'youtube', 'https://youtube.com/awaan', 'text', 'general', '2026-03-10 13:49:17', '2026-06-26 02:27:13'),
(20, 'linkedin', 'https://linkedin.com/awaan', 'text', 'general', '2026-03-10 13:49:17', '2026-06-26 02:27:13'),
(21, 'meta_title', NULL, 'text', 'general', '2026-03-10 13:49:17', '2026-03-10 13:49:17'),
(22, 'google_analytics', NULL, 'text', 'general', '2026-03-10 13:49:17', '2026-03-10 13:49:17'),
(23, 'show_product_price', '0', 'text', 'general', '2026-03-10 13:53:55', '2026-04-16 16:15:22'),
(24, 'logo', 'assets/images/logo.png', 'text', 'general', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(25, 'favicon', NULL, 'file', 'general', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(26, 'default_currency', 'SAR', 'text', 'general', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(27, 'default_language', 'ar', 'text', 'general', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(28, 'timezone', 'Asia/Riyadh', 'text', 'general', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(29, 'og_image', NULL, 'file', 'seo', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(30, 'contact_instagram', 'https://www.instagram.com/awaan_altakadm.co?igsh=MjQydTZmZTkweXg3', 'text', 'contact', '2026-06-26 05:12:13', '2026-06-26 02:27:13'),
(31, 'contact_twitter', 'https://x.com/awaan', 'text', 'contact', '2026-06-26 05:12:13', '2026-06-26 02:27:13'),
(32, 'contact_youtube', 'https://youtube.com/awaan', 'text', 'contact', '2026-06-26 05:12:13', '2026-06-26 02:27:13'),
(33, 'contact_linkedin', 'https://linkedin.com/awaan', 'text', 'contact', '2026-06-26 05:12:13', '2026-06-26 02:27:13'),
(34, 'email_notifications', '1', 'boolean', 'notifications', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(35, 'sms_notifications', '0', 'boolean', 'notifications', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(36, 'push_notifications', '0', 'boolean', 'notifications', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(37, 'system_notifications', '1', 'boolean', 'notifications', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(38, 'about_title', 'من نحن', 'text', 'about', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(39, 'about_description', 'نحن في أوان التقدم نقدم مستلزمات البناء التي تجمع بين الجودة العالمية والعصرية في التصميم، لنكون شريكك الأمثل في مشاريعك الإنشائية.', 'textarea', 'about', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(40, 'about_story', 'في عالم متسارع يتطلب البناء فيه الجمع بين القوة والجمال، ولدت أوان التقدم.لم نكن نريد مجرد توريد مواد بناء، بل أردنا تغيير الطريقة التي تُبنى بها المشاريع. انطلقنا برؤية واضحة: سد الفجوة بين الجودة العالمية الصارمة والتصاميم العصرية الحديثة.كل حجر، وكل قطعة، وكل مادة نوفرها اليوم هي نتاج بحث دقيق وشراكات عالمية. نحن لا نبيع مستلزمات إنشائية فحسب، بل نقدم لعملائنا ومطورينا الطمأنينة والأمان لتبديل المخططات الورقية إلى واقع ملموس يدوم لأجيال.اليوم، نفخر بأننا لسنا مجرد موردين، بل الشريك الأمثل لقصص نجاح معمارية تزيّن حاضرنا وتبني مستقبلك.', 'textarea', 'about', '2026-06-26 05:12:13', '2026-06-26 02:58:55'),
(41, 'about_values', 'نختار منتجاتنا بعناية من أفضل الموردين العالميين.\\nنبحث عن أحدث التقنيات والحلول العصرية.\\nنبني علاقات طويلة الأمد مبنية على الشفافية.\\nفريق متخصص جاهز لتقديم الدعم في كل خطوة.', 'textarea', 'about', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(42, 'about_services', 'أدوات صحية وعصرية: تشكيلة راقية من أطقم الحمامات والخلاطات التي تدمج بين كفاءة استهلاك المياه وجمال التصميم الحديث.\r\nأنظمة إضاءة ذكية: حلول إنارة داخلية وخارجية متطورة توفر الطاقة وتمنح المباني لمسة معمارية ساحرة.\r\nسيراميك وبورسلان فاخر: أرضيات وجدران بألوان ونقشات عصرية تحاكي أحدث صيحات الديكور العالمي وتتحمل الاستخدام الشاق.\r\nمواد عزل وحماية: مستلزمات عزل مائي وحراري عالية الكفاءة لحماية المنشآت من التغيرات المناخية وضمان استدامتها.\r\nاجهات زجاجية وكلادينج: حلول متكاملة لكسوة المباني الخارجية تمنح المشاريع التجارية والسكنية مظهراً مستقبلياً فخماً.\r\nاستشارات وتوريد للمشاريع: خدمة دعم فني متخصصة لمساعدة المقاولين في اختيار المواد الأنسب وتوريدها بدقة وفي الموعد.', 'textarea', 'about', '2026-06-26 05:12:13', '2026-06-26 02:30:24'),
(43, 'about_years', '15', 'text', 'about', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(44, 'about_projects', '1000', 'text', 'about', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(45, 'about_customers', '5000', 'text', 'about', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(46, 'about_partners', '200', 'text', 'about', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(47, 'about_title_en', 'About Us', 'text', 'about', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(48, 'about_description_en', 'We at Awaan Al-Takadom provide building materials that combine global quality with modern design, making us your ideal partner for your construction projects.', 'textarea', 'about', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(49, 'about_story_en', 'In a fast-paced world where construction requires a combination of strength and beauty, Awan Progress was born. We didn\'t just want to supply building materials, we wanted to change the way projects are built. We set out with a clear vision: to bridge the gap between stringent international quality and modern, contemporary designs. Every stone, every piece, and every material we offer today is the result of meticulous research and global partnerships. We do not just sell construction supplies, but we also provide our customers and developers with the reassurance and security to turn paper plans into a tangible reality that will last for generations. Today, we are proud to be not just suppliers, but rather the ideal partner for architectural success stories that decorate our present and build your future.', 'textarea', 'about', '2026-06-26 05:12:13', '2026-06-26 02:58:55'),
(50, 'about_values_en', 'We carefully select our products from the best global suppliers.\\nWe seek the latest technologies and modern solutions.\\nWe build long-term relationships based on transparency.\\nA specialized team ready to provide support at every step.', 'textarea', 'about', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(51, 'about_services_en', 'Modern Sanitary Ware: A refined selection of bathroom fixtures and mixers that blend water efficiency with modern design aesthetics.\r\nSmart Lighting Systems: Advanced indoor and outdoor lighting solutions that save energy and give buildings a stunning architectural touch.\r\nPremium Ceramics & Porcelain: Floors and walls with modern colors and patterns that mimic the latest global decor trends and withstand heavy use.\r\nInsulation & Protection Materials: High-efficiency waterproofing and thermal insulation materials to protect structures from climate changes and ensure sustainability.\r\nGlass Facades & Cladding: Integrated building exterior cladding solutions that give commercial and residential projects a luxurious futuristic look.\r\nProject Consulting & Supply: Specialized technical support service to help contractors select the most suitable materials and supply them accurately and on time.', 'textarea', 'about', '2026-06-26 05:12:13', '2026-06-26 02:32:05'),
(52, 'about_value_1_title', 'الجودة العالمية', 'text', 'about', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(53, 'about_value_1_title_en', 'Global Quality', 'text', 'about', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(54, 'about_value_1_desc', 'نلتزم بأعلى معايير الكفاءة والمتانة في كل منتج نقدّمه لضمان استدامة مشاريعكم.', 'text', 'about', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(55, 'about_value_1_desc_en', 'We adhere to the highest standards of efficiency and durability in every product we offer to ensure the sustainability of your projects.', 'text', 'about', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(56, 'about_value_2_title', 'الابتكار والعصرية', 'text', 'about', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(57, 'about_value_2_title_en', 'Innovation & Modernity', 'text', 'about', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(58, 'about_value_2_desc', 'نواكب أحدث توجهات التصميم المعماري لنمنح مشاريعكم لمسة جمالية متجددة.', 'text', 'about', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(59, 'about_value_2_desc_en', 'We keep pace with the latest architectural design trends to give your projects a renewed aesthetic touch.', 'text', 'about', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(60, 'about_value_3_title', 'الشراكة الحقيقية', 'text', 'about', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(61, 'about_value_3_title_en', 'True Partnership', 'text', 'about', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(62, 'about_value_3_desc', 'لا نرى عملاءنا كمشترين، بل كشركاء نجاح نرافقهم خطوة بخطوة حتى اكتمال البناء.', 'text', 'about', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(63, 'about_value_3_desc_en', 'We don\'t see our customers as buyers, but as success partners we accompany step by step until construction is complete.', 'text', 'about', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(64, 'about_value_4_title', 'النزاهة والشفافية', 'text', 'about', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(65, 'about_value_4_title_en', 'Integrity & Transparency', 'text', 'about', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(66, 'about_value_4_desc', 'نلتزم بالصدق والأمانة في التعامل، والوضوح التام في مواصفات المنتجات ومواعيد التسليم.', 'text', 'about', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(67, 'about_value_4_desc_en', 'We are committed to honesty and integrity in dealings, and complete clarity in product specifications and delivery dates.', 'text', 'about', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(68, 'about_value_5_title', 'الاستدامة والمسؤولية', 'text', 'about', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(69, 'about_value_5_title_en', 'Sustainability & Responsibility', 'text', 'about', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(70, 'about_value_5_desc', 'نحرص على توفير مستلزمات بناء صديقة للبيئة تساهم في إعمار مستقبل آمن وصحي.', 'text', 'about', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(71, 'about_value_5_desc_en', 'We are keen to provide environmentally friendly building materials that contribute to building a safe and healthy future.', 'text', 'about', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(72, 'vision_title', 'هويتنا ورؤيتنا', 'text', 'vision', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(73, 'vision_description', 'نسعى لأن نكون الخيار الأول في سوق مستلزمات البناء في سورية والمنطقة، من خلال تقديم منتجات عالمية بمعايير جودة عالية وخدمة لا مثيل لها.', 'textarea', 'vision', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(74, 'vision_feature_1_title', 'جودة عالمية', 'text', 'vision', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(75, 'vision_feature_1_description', 'نعمل مع أكبر الموردين العالميين لتقديم مستلزمات بناء تلبي أعلى معايير الجودة الدولية. كل منتج نقدمه يخضع لعمليات فحص ورقابة صارمة لضمان التميز.', 'textarea', 'vision', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(76, 'vision_feature_2_title', 'تصميم عصري', 'text', 'vision', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(77, 'vision_feature_2_description', 'نواكب أحدث صرحتات التصميم المعماري والديكور الداخلي لنقدم لكم منتجات تجمع بين الجمال والوظيفية. نؤمن بأن التصميم الجيد يبدأ باختيار المواد المناسبة.', 'textarea', 'vision', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(78, 'vision_feature_3_title', 'شراكة موثوقة', 'text', 'vision', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(79, 'vision_feature_3_description', 'نبني مع شركائنا علاقات استراتيجية طويلة الأمد ترتكز على الثقة والشفافية والمنفعة المشتركة. ن视 أنفسنا شريكاً حقيقياً في نجاح مشاريعكم الإنشائية.', 'textarea', 'vision', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(80, 'vision_title_en', 'Our Identity & Vision', 'text', 'vision', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(81, 'vision_description_en', 'We aspire to be the first choice in the building materials market in Syria and the region, by providing global products with high quality standards and unparalleled service.', 'textarea', 'vision', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(82, 'vision_feature_1_title_en', 'Global Quality', 'text', 'vision', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(83, 'vision_feature_1_description_en', 'We work with the world\'s largest suppliers to provide building materials that meet the highest international quality standards. Every product we offer undergoes strict inspection and quality control processes.', 'textarea', 'vision', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(84, 'vision_feature_2_title_en', 'Modern Design', 'text', 'vision', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(85, 'vision_feature_2_description_en', 'We keep pace with the latest architectural and interior design trends to offer you products that combine beauty and functionality. We believe that good design starts with choosing the right materials.', 'textarea', 'vision', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(86, 'vision_feature_3_title_en', 'Trusted Partnership', 'text', 'vision', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(87, 'vision_feature_3_description_en', 'We build long-term strategic relationships with our partners based on trust, transparency, and mutual benefit. We see ourselves as a true partner in the success of your construction projects.', 'textarea', 'vision', '2026-06-26 05:12:13', '2026-06-26 05:12:13'),
(112, 'site_name_en', 'Awaan Altakadom', 'text', 'general', '2026-06-26 02:27:13', '2026-06-26 02:27:13'),
(113, 'site_tagline_en', 'Building a better tomorrow', 'text', 'general', '2026-06-26 02:27:13', '2026-06-26 02:27:13'),
(114, 'site_description_en', 'At Awan Al Taqaddam, we offer building supplies that combine global quality with modern design, to be your ideal partner in your construction projects.', 'text', 'general', '2026-06-26 02:27:13', '2026-06-26 02:27:58'),
(115, 'theme_primary_color', '#1e3a8a', 'text', 'general', '2026-06-26 02:27:13', '2026-06-26 02:33:29'),
(116, 'theme_primary_light_color', '#3b82f6', 'text', 'general', '2026-06-26 02:27:13', '2026-06-26 02:33:29'),
(117, 'theme_primary_dark_color', '#1e1b4b', 'text', 'general', '2026-06-26 02:27:13', '2026-06-26 02:33:29'),
(118, 'theme_secondary_color', '#06b6d4', 'text', 'general', '2026-06-26 02:27:13', '2026-06-26 02:33:29'),
(119, 'theme_secondary_light_color', '#67e8f9', 'text', 'general', '2026-06-26 02:27:13', '2026-06-26 02:33:29'),
(120, 'theme_accent_color', '#f59e0b', 'text', 'general', '2026-06-26 02:27:13', '2026-06-26 02:33:29'),
(121, 'theme_accent_light_color', '#fbbf24', 'text', 'general', '2026-06-26 02:27:13', '2026-06-26 02:33:29'),
(122, 'theme_font_family', 'Cairo', 'text', 'general', '2026-06-26 02:27:13', '2026-06-26 02:27:13'),
(123, 'theme_border_radius', '14px', 'text', 'general', '2026-06-26 02:27:13', '2026-06-26 02:27:13'),
(124, 'theme_hero_align', 'center', 'text', 'general', '2026-06-26 02:27:13', '2026-06-26 02:27:13'),
(125, 'theme_hero_overlay_opacity', '0.5', 'text', 'general', '2026-06-26 02:27:13', '2026-06-26 02:27:13'),
(126, 'theme_footer_layout', 'multicolumn', 'text', 'general', '2026-06-26 02:27:13', '2026-06-26 02:27:13'),
(127, 'theme_custom_css', NULL, 'text', 'general', '2026-06-26 02:27:13', '2026-06-26 02:27:13'),
(128, 'theme_navbar_bg_color', '#1e3a8a', 'text', 'general', '2026-06-26 02:27:13', '2026-06-26 02:33:29'),
(129, 'theme_navbar_text_color', '#ffffff', 'text', 'general', '2026-06-26 02:27:13', '2026-06-26 02:33:29'),
(130, 'theme_navbar_scrolled_bg_color', '#1e3a8a', 'text', 'general', '2026-06-26 02:27:13', '2026-06-26 02:33:29'),
(131, 'theme_navbar_scrolled_text_color', '#ffffff', 'text', 'general', '2026-06-26 02:27:13', '2026-06-26 02:33:29'),
(132, 'theme_navbar_transparency', '25', 'text', 'general', '2026-06-26 02:27:13', '2026-06-26 02:27:13'),
(133, 'theme_hero_btn_bg_color', 'linear-gradient(135deg, #1e3a8a, #3b82f6)', 'text', 'general', '2026-06-26 02:27:13', '2026-06-26 02:33:29'),
(134, 'theme_hero_btn_text_color', '#ffffff', 'text', 'general', '2026-06-26 02:27:13', '2026-06-26 02:33:29'),
(135, 'theme_hero_btn_secondary_bg_color', 'rgba(255, 255, 255, 0.1)', 'text', 'general', '2026-06-26 02:27:13', '2026-06-26 02:33:29'),
(136, 'theme_hero_btn_secondary_text_color', '#1e3a8a', 'text', 'general', '2026-06-26 02:27:13', '2026-06-26 02:33:29'),
(137, 'theme_cart_btn_bg_color', '#1e3a8a', 'text', 'general', '2026-06-26 02:27:13', '2026-06-26 02:33:29'),
(138, 'theme_cart_btn_text_color', '#ffffff', 'text', 'general', '2026-06-26 02:27:13', '2026-06-26 02:33:29'),
(139, 'theme_footer_bg_color', '#1e1b4b', 'text', 'general', '2026-06-26 02:27:13', '2026-06-26 02:33:29'),
(140, 'theme_footer_text_color', '#e2e8f0', 'text', 'general', '2026-06-26 02:27:13', '2026-06-26 02:33:29'),
(141, 'theme_page_header_bg_color', 'linear-gradient(135deg, #1e3a8a, #3b82f6)', 'text', 'general', '2026-06-26 02:27:13', '2026-06-26 02:33:29'),
(142, 'theme_page_header_text_color', '#ffffff', 'text', 'general', '2026-06-26 02:27:13', '2026-06-26 02:33:29'),
(143, 'address_en', 'Kingdom of Saudi Arabia - Riyadh\r\nSyria - Damascus', 'text', 'general', '2026-06-26 03:18:15', '2026-06-26 03:25:06'),
(144, 'contact_address_en', 'Kingdom of Saudi Arabia - Riyadh\r\nSyria - Damascus', 'text', 'general', '2026-06-26 03:18:15', '2026-06-26 03:25:06'),
(145, 'working_hours_en', 'Sunday - Thursday 09:00 - 17:00', 'text', 'general', '2026-06-26 03:18:15', '2026-06-26 03:23:05'),
(146, 'meta_title_en', NULL, 'text', 'general', '2026-06-26 03:18:15', '2026-06-26 03:18:15'),
(147, 'meta_description_en', NULL, 'text', 'general', '2026-06-26 03:18:15', '2026-06-26 03:18:15'),
(148, 'meta_keywords_en', NULL, 'text', 'general', '2026-06-26 03:18:15', '2026-06-26 03:18:15');

-- --------------------------------------------------------

--
-- بنية الجدول `shipping_manifests`
--

CREATE TABLE `shipping_manifests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `warehouse_id` bigint(20) UNSIGNED NOT NULL,
  `manifest_number` varchar(255) NOT NULL,
  `carrier_id` bigint(20) UNSIGNED DEFAULT NULL,
  `carrier_name` varchar(255) DEFAULT NULL,
  `status` enum('pending','in_transit','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `shipping_date` date NOT NULL,
  `estimated_delivery` date DEFAULT NULL,
  `actual_delivery` date DEFAULT NULL,
  `total_packages` int(11) NOT NULL DEFAULT 0,
  `total_weight` decimal(10,2) NOT NULL DEFAULT 0.00,
  `shipping_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tracking_number` varchar(255) DEFAULT NULL,
  `route` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`route`)),
  `driver_id` bigint(20) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `shipping_manifest_items`
--

CREATE TABLE `shipping_manifest_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `shipping_manifest_id` bigint(20) UNSIGNED NOT NULL,
  `packing_list_id` bigint(20) UNSIGNED NOT NULL,
  `sales_order_id` bigint(20) UNSIGNED NOT NULL,
  `tracking_number` varchar(255) DEFAULT NULL,
  `package_number` varchar(255) NOT NULL,
  `weight` decimal(10,2) DEFAULT NULL,
  `dimensions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dimensions`)),
  `delivery_address` varchar(255) DEFAULT NULL,
  `recipient_name` varchar(255) DEFAULT NULL,
  `recipient_phone` varchar(255) DEFAULT NULL,
  `delivery_status` enum('pending','in_transit','delivered','failed') NOT NULL DEFAULT 'pending',
  `delivered_at` timestamp NULL DEFAULT NULL,
  `signature` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `site_visitors`
--

CREATE TABLE `site_visitors` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `visit_count` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `page_url` text DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `visit_date` date NOT NULL,
  `visit_time` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
PARTITION BY RANGE COLUMNS(`visit_date`)
(
PARTITION p2026_q1 VALUES LESS THAN ('2026-04-01') ENGINE=InnoDB,
PARTITION p2026_q2 VALUES LESS THAN ('2026-07-01') ENGINE=InnoDB,
PARTITION p2026_q3 VALUES LESS THAN ('2026-10-01') ENGINE=InnoDB,
PARTITION p2026_q4 VALUES LESS THAN ('2027-01-01') ENGINE=InnoDB,
PARTITION p_future VALUES LESS THAN (MAXVALUE) ENGINE=InnoDB
);

--
-- إرجاع أو استيراد بيانات الجدول `site_visitors`
--

INSERT INTO `site_visitors` (`id`, `created_at`, `updated_at`, `ip_address`, `visit_count`, `page_url`, `user_agent`, `visit_date`, `visit_time`) VALUES
(1, '2026-03-09 12:07:47', '2026-03-09 20:10:37', '127.0.0.1', 7, 'http://attakadom.test', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09', '23:10:37'),
(2, '2026-03-09 21:34:10', '2026-03-10 19:38:09', '127.0.0.1', 39, 'http://attakadom.test', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10', '22:38:09'),
(3, '2026-03-11 04:05:37', '2026-03-11 06:08:26', '127.0.0.1', 38, 'http://attakadom.test', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-11', '09:08:26'),
(4, '2026-03-12 18:26:53', '2026-03-12 18:57:31', '127.0.0.1', 3, 'http://attakadom.test', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-12', '21:57:31'),
(5, '2026-03-12 23:50:51', '2026-03-13 00:04:05', '127.0.0.1', 16, 'http://attakadom.test', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13', '03:04:05'),
(6, '2026-03-14 17:41:18', '2026-03-14 20:37:47', '127.0.0.1', 22, 'http://awan.test', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-14', '23:37:47'),
(7, '2026-03-14 19:00:12', '2026-03-14 19:13:54', '::1', 9, 'http://localhost/awan/public', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-14', '22:13:54'),
(8, '2026-03-15 19:07:07', '2026-03-15 19:07:07', '127.0.0.1', 1, 'http://awan.test', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15', '22:07:07'),
(9, '2026-03-27 16:44:38', '2026-03-27 16:46:22', '127.0.0.1', 3, 'http://awan.test/featured-products', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27', '19:46:22'),
(10, '2026-03-28 14:42:42', '2026-03-28 14:44:50', '127.0.0.1', 3, 'http://awan.test', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28', '17:44:50'),
(11, '2026-03-29 17:22:36', '2026-03-29 17:56:25', '127.0.0.1', 6, 'http://awan.test', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29', '20:56:25'),
(12, '2026-04-02 14:42:42', '2026-04-02 14:43:23', '127.0.0.1', 2, 'http://awan.test', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02', '17:43:23'),
(13, '2026-04-10 23:44:59', '2026-04-11 00:53:24', '127.0.0.1', 11, 'http://awan.test', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11', '03:53:24'),
(14, '2026-04-12 17:33:55', '2026-04-12 17:33:55', '127.0.0.1', 1, 'http://awan.test', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-12', '20:33:55'),
(15, '2026-04-14 13:20:46', '2026-04-14 16:28:37', '127.0.0.1', 9, 'http://awan.test', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-14', '19:28:37'),
(16, '2026-04-16 11:40:44', '2026-04-16 16:51:03', '127.0.0.1', 12, 'http://awan.test', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-16', '19:51:03'),
(17, '2026-04-17 02:58:42', '2026-04-17 03:01:16', '127.0.0.1', 2, 'http://awan.test', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-17', '06:01:16'),
(18, '2026-04-18 14:18:01', '2026-04-18 15:09:10', '127.0.0.1', 4, 'http://awan.test', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18', '18:09:10'),
(19, '2026-04-18 21:39:56', '2026-04-19 17:34:58', '127.0.0.1', 25, 'http://awan.test/featured-products?page=3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19', '20:34:58'),
(20, '2026-04-24 13:08:20', '2026-04-24 13:08:20', '127.0.0.1', 1, 'http://awan.test', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-24', '16:08:20'),
(21, '2026-04-25 13:22:21', '2026-04-25 15:59:17', '127.0.0.1', 10, 'http://awan.test', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-25', '18:59:17');

-- --------------------------------------------------------

--
-- بنية الجدول `special_offers`
--

CREATE TABLE `special_offers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title_ar` varchar(255) NOT NULL,
  `title_en` varchar(255) DEFAULT NULL,
  `description_ar` text NOT NULL,
  `description_en` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `discount_percentage` decimal(5,2) DEFAULT NULL,
  `product_id` bigint(20) UNSIGNED DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `movement_type` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `reference` varchar(255) DEFAULT NULL,
  `source` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `warehouse_id` bigint(20) UNSIGNED DEFAULT NULL,
  `unit_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_cost` decimal(14,2) NOT NULL DEFAULT 0.00,
  `batch_number` varchar(255) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `subscribers`
--

CREATE TABLE `subscribers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `suppliers`
--

CREATE TABLE `suppliers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `tax_number` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `state` varchar(255) DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `postal_code` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `balance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `credit_limit` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(255) NOT NULL DEFAULT 'SAR',
  `lead_time_days` varchar(255) DEFAULT NULL,
  `total_purchases` decimal(14,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `tickets`
--

CREATE TABLE `tickets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `customer_id` bigint(20) UNSIGNED NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'open',
  `priority` varchar(255) NOT NULL DEFAULT 'medium',
  `assigned_to` bigint(20) UNSIGNED DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `is_pro` tinyint(1) NOT NULL DEFAULT 0,
  `pro_label` varchar(255) DEFAULT NULL,
  `notifications_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `role_id` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `avatar`, `is_pro`, `pro_label`, `notifications_enabled`, `is_admin`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`, `role_id`) VALUES
(1, 'Issa', 'admin@admin.com', NULL, 0, NULL, 1, 1, '2026-03-09 12:07:26', '$2y$12$VEaW0OUHVKfkLlU8lMBjwuZg7j8/2nXceR8P82dSCWZU0vFKAw99K', 'cwWmXXE6agF6g2yf16OMmclEk2p4BaYhmBfJyaGy0xLKfbGKKkvDfT0ePvb0', '2026-03-09 12:07:26', '2026-03-11 05:14:29', NULL),
(2, 'Test User', 'test@example.com', NULL, 0, NULL, 1, 0, '2026-03-09 12:07:27', '$2y$12$oMRS6Qxh/ezs7IhpizvUau1gSaqrdRpRLiQYVIg490aZv0cS9ioSe', 'cjka7tbR3v', '2026-03-09 12:07:27', '2026-03-09 12:07:27', NULL);

-- --------------------------------------------------------

--
-- بنية الجدول `visitors`
--

CREATE TABLE `visitors` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `page_url` varchar(255) DEFAULT NULL,
  `referrer` varchar(255) DEFAULT NULL,
  `country` varchar(2) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `device_type` varchar(20) DEFAULT NULL,
  `browser` varchar(50) DEFAULT NULL,
  `os` varchar(50) DEFAULT NULL,
  `is_bot` tinyint(1) NOT NULL DEFAULT 0,
  `visited_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `visitors`
--

INSERT INTO `visitors` (`id`, `ip_address`, `user_agent`, `page_url`, `referrer`, `country`, `city`, `device_type`, `browser`, `os`, `is_bot`, `visited_at`, `created_at`, `updated_at`) VALUES
(1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/product/architectural-building-solutions-stair-nosing', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 04:28:59', '2026-03-11 04:28:59', '2026-03-11 04:28:59'),
(2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/categories', 'http://attakadom.test/product/architectural-building-solutions-stair-nosing', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 04:30:00', '2026-03-11 04:30:00', '2026-03-11 04:30:00'),
(3, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/category/cable-management-systems', 'http://attakadom.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 04:30:03', '2026-03-11 04:30:03', '2026-03-11 04:30:03'),
(4, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/product/cable-management-systems-cable-ladder-basket-tray', 'http://attakadom.test/category/cable-management-systems', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 04:30:07', '2026-03-11 04:30:07', '2026-03-11 04:30:07'),
(5, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/inquiry', 'http://attakadom.test/product/cable-management-systems-cable-ladder-basket-tray', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 04:33:10', '2026-03-11 04:33:10', '2026-03-11 04:33:10'),
(6, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/inquiry', 'http://attakadom.test/inquiry', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 04:33:34', '2026-03-11 04:33:34', '2026-03-11 04:33:34'),
(7, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/inquiry', 'http://attakadom.test/inquiry', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 04:33:36', '2026-03-11 04:33:36', '2026-03-11 04:33:36'),
(8, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/inquiry', 'http://attakadom.test/inquiry', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 04:36:16', '2026-03-11 04:36:16', '2026-03-11 04:36:16'),
(9, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/inquiry', 'http://attakadom.test/inquiry', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 04:36:16', '2026-03-11 04:36:16', '2026-03-11 04:36:16'),
(10, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/categories', 'http://attakadom.test/inquiry', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 04:40:00', '2026-03-11 04:40:00', '2026-03-11 04:40:00'),
(11, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/category/cable-management-systems', 'http://attakadom.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 04:40:04', '2026-03-11 04:40:04', '2026-03-11 04:40:04'),
(12, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/product/cable-management-systems-cable-ladder-basket-tray', 'http://attakadom.test/category/cable-management-systems', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 04:40:12', '2026-03-11 04:40:12', '2026-03-11 04:40:12'),
(13, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/product/cable-management-systems-cable-ladder-basket-tray', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 04:40:14', '2026-03-11 04:40:14', '2026-03-11 04:40:14'),
(14, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/product/cable-management-systems-cable-ladder-basket-tray', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 04:40:19', '2026-03-11 04:40:19', '2026-03-11 04:40:19'),
(15, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/product/cable-management-systems-cable-ladder-basket-tray', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 04:40:33', '2026-03-11 04:40:33', '2026-03-11 04:40:33'),
(16, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/product/cable-management-systems-upvc-conduits', 'http://attakadom.test/product/cable-management-systems-cable-ladder-basket-tray', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 04:41:50', '2026-03-11 04:41:50', '2026-03-11 04:41:50'),
(17, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/product/cable-management-systems-control-panel-boxes', 'http://attakadom.test/product/cable-management-systems-upvc-conduits', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 04:41:54', '2026-03-11 04:41:54', '2026-03-11 04:41:54'),
(18, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/categories', 'http://attakadom.test/product/cable-management-systems-control-panel-boxes', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 04:45:02', '2026-03-11 04:45:02', '2026-03-11 04:45:02'),
(19, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/category/blockwork-plastering-accessories', 'http://attakadom.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 04:45:06', '2026-03-11 04:45:06', '2026-03-11 04:45:06'),
(20, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/product/blockwork-plastering-accessories-lintel-block-ties', 'http://attakadom.test/category/blockwork-plastering-accessories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 04:45:10', '2026-03-11 04:45:10', '2026-03-11 04:45:10'),
(21, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/product/blockwork-plastering-accessories-corner-mesh', 'http://attakadom.test/product/blockwork-plastering-accessories-lintel-block-ties', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 04:45:16', '2026-03-11 04:45:16', '2026-03-11 04:45:16'),
(22, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/product/blockwork-plastering-accessories-control-joint', 'http://attakadom.test/product/blockwork-plastering-accessories-corner-mesh', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 04:45:20', '2026-03-11 04:45:20', '2026-03-11 04:45:20'),
(23, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/logout', 'http://attakadom.test/admin/visitors', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 04:53:14', '2026-03-11 04:53:14', '2026-03-11 04:53:14'),
(24, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', 'http://attakadom.test/admin/visitors', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 04:53:15', '2026-03-11 04:53:15', '2026-03-11 04:53:15'),
(25, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/admin', 'http://attakadom.test/login', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 04:53:26', '2026-03-11 04:53:26', '2026-03-11 04:53:26'),
(26, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/admin', 'http://attakadom.test/admin/settings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 04:58:41', '2026-03-11 04:58:41', '2026-03-11 04:58:41'),
(27, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/admin', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 05:06:55', '2026-03-11 05:06:55', '2026-03-11 05:06:55'),
(28, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', 'http://attakadom.test/admin/settings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 05:16:17', '2026-03-11 05:16:17', '2026-03-11 05:16:17'),
(29, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/admin', 'http://attakadom.test/admin/settings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 05:16:22', '2026-03-11 05:16:22', '2026-03-11 05:16:22'),
(30, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', 'http://attakadom.test/admin', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 05:16:31', '2026-03-11 05:16:31', '2026-03-11 05:16:31'),
(31, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/product/blockwork-plastering-accessories-control-joint', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 05:21:23', '2026-03-11 05:21:23', '2026-03-11 05:21:23'),
(32, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/product/blockwork-plastering-accessories-control-joint', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 05:23:29', '2026-03-11 05:23:29', '2026-03-11 05:23:29'),
(33, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/logout', 'http://attakadom.test/admin', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 05:23:41', '2026-03-11 05:23:41', '2026-03-11 05:23:41'),
(34, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', 'http://attakadom.test/admin', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 05:23:42', '2026-03-11 05:23:42', '2026-03-11 05:23:42'),
(35, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 05:23:46', '2026-03-11 05:23:46', '2026-03-11 05:23:46'),
(36, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 05:25:39', '2026-03-11 05:25:39', '2026-03-11 05:25:39'),
(37, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/admin', 'http://attakadom.test/login', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 05:26:40', '2026-03-11 05:26:40', '2026-03-11 05:26:40'),
(38, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 05:32:20', '2026-03-11 05:32:20', '2026-03-11 05:32:20'),
(39, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 05:33:55', '2026-03-11 05:33:55', '2026-03-11 05:33:55'),
(40, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Firefox', 'Windows', 0, '2026-03-11 05:36:40', '2026-03-11 05:36:40', '2026-03-11 05:36:40'),
(41, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 05:36:58', '2026-03-11 05:36:58', '2026-03-11 05:36:58'),
(42, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Firefox', 'Windows', 0, '2026-03-11 05:37:10', '2026-03-11 05:37:10', '2026-03-11 05:37:10'),
(43, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Firefox', 'Windows', 0, '2026-03-11 05:37:40', '2026-03-11 05:37:40', '2026-03-11 05:37:40'),
(44, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Firefox', 'Windows', 0, '2026-03-11 05:37:42', '2026-03-11 05:37:42', '2026-03-11 05:37:42'),
(45, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 05:38:57', '2026-03-11 05:38:57', '2026-03-11 05:38:57'),
(46, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 05:38:57', '2026-03-11 05:38:57', '2026-03-11 05:38:57'),
(47, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 05:39:44', '2026-03-11 05:39:44', '2026-03-11 05:39:44'),
(48, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/logout', 'http://attakadom.test/admin/settings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 05:39:52', '2026-03-11 05:39:52', '2026-03-11 05:39:52'),
(49, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', 'http://attakadom.test/admin/settings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 05:39:52', '2026-03-11 05:39:52', '2026-03-11 05:39:52'),
(50, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 05:41:11', '2026-03-11 05:41:11', '2026-03-11 05:41:11'),
(51, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 05:43:33', '2026-03-11 05:43:33', '2026-03-11 05:43:33'),
(52, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', 'http://attakadom.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 05:43:41', '2026-03-11 05:43:41', '2026-03-11 05:43:41'),
(53, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', 'http://attakadom.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 05:43:47', '2026-03-11 05:43:47', '2026-03-11 05:43:47'),
(54, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 05:46:23', '2026-03-11 05:46:23', '2026-03-11 05:46:23'),
(55, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 05:46:40', '2026-03-11 05:46:40', '2026-03-11 05:46:40'),
(56, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', 'http://attakadom.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 05:46:42', '2026-03-11 05:46:42', '2026-03-11 05:46:42'),
(57, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 05:47:59', '2026-03-11 05:47:59', '2026-03-11 05:47:59'),
(58, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 05:50:00', '2026-03-11 05:50:00', '2026-03-11 05:50:00'),
(59, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', 'http://attakadom.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 05:50:05', '2026-03-11 05:50:05', '2026-03-11 05:50:05'),
(60, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 05:51:54', '2026-03-11 05:51:54', '2026-03-11 05:51:54'),
(61, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', 'http://attakadom.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 05:52:08', '2026-03-11 05:52:08', '2026-03-11 05:52:08'),
(62, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/categories', 'http://attakadom.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 05:52:16', '2026-03-11 05:52:16', '2026-03-11 05:52:16'),
(63, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/categories', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 05:53:00', '2026-03-11 05:53:00', '2026-03-11 05:53:00'),
(64, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/categories', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 05:54:35', '2026-03-11 05:54:35', '2026-03-11 05:54:35'),
(65, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/admin', 'http://attakadom.test/login', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 05:54:54', '2026-03-11 05:54:54', '2026-03-11 05:54:54'),
(66, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/categories', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 05:55:08', '2026-03-11 05:55:08', '2026-03-11 05:55:08'),
(67, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Firefox', 'Windows', 0, '2026-03-11 05:55:24', '2026-03-11 05:55:24', '2026-03-11 05:55:24'),
(68, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', 'http://attakadom.test/admin', 'http://attakadom.test/login', 'SY', NULL, 'desktop', 'Firefox', 'Windows', 0, '2026-03-11 05:55:41', '2026-03-11 05:55:41', '2026-03-11 05:55:41'),
(69, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', 'http://attakadom.test', 'http://attakadom.test/admin', 'SY', NULL, 'desktop', 'Firefox', 'Windows', 0, '2026-03-11 05:55:43', '2026-03-11 05:55:43', '2026-03-11 05:55:43'),
(70, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Firefox', 'Windows', 0, '2026-03-11 05:55:46', '2026-03-11 05:55:46', '2026-03-11 05:55:46'),
(71, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/categories', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 05:56:50', '2026-03-11 05:56:50', '2026-03-11 05:56:50'),
(72, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/categories', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 05:57:27', '2026-03-11 05:57:27', '2026-03-11 05:57:27'),
(73, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/categories', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 05:59:30', '2026-03-11 05:59:30', '2026-03-11 05:59:30'),
(74, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/contact', 'http://attakadom.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 05:59:42', '2026-03-11 05:59:42', '2026-03-11 05:59:42'),
(75, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/contact', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 06:00:50', '2026-03-11 06:00:50', '2026-03-11 06:00:50'),
(76, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/contact', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 06:01:24', '2026-03-11 06:01:24', '2026-03-11 06:01:24'),
(77, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/about', 'http://attakadom.test/contact', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 06:01:37', '2026-03-11 06:01:37', '2026-03-11 06:01:37'),
(78, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/featured-products', 'http://attakadom.test/about', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 06:01:55', '2026-03-11 06:01:55', '2026-03-11 06:01:55'),
(79, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/categories', 'http://attakadom.test/featured-products', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 06:02:00', '2026-03-11 06:02:00', '2026-03-11 06:02:00'),
(80, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/about', 'http://attakadom.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 06:02:05', '2026-03-11 06:02:05', '2026-03-11 06:02:05'),
(81, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/contact', 'http://attakadom.test/about', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 06:02:40', '2026-03-11 06:02:40', '2026-03-11 06:02:40'),
(82, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/about', 'http://attakadom.test/contact', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 06:05:28', '2026-03-11 06:05:28', '2026-03-11 06:05:28'),
(83, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/contact', 'http://attakadom.test/about', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-11 06:06:04', '2026-03-11 06:06:04', '2026-03-11 06:06:04'),
(84, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 'http://attakadom.test', 'http://attakadom.test/contact', 'SY', NULL, 'mobile', 'Chrome', 'Linux', 0, '2026-03-11 06:06:41', '2026-03-11 06:06:41', '2026-03-11 06:06:41'),
(85, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 'http://attakadom.test/category/cable-management-systems', 'http://attakadom.test/', 'SY', NULL, 'mobile', 'Chrome', 'Linux', 0, '2026-03-11 06:06:48', '2026-03-11 06:06:48', '2026-03-11 06:06:48'),
(86, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 'http://attakadom.test/product/cable-management-systems-control-panel-boxes', 'http://attakadom.test/category/cable-management-systems', 'SY', NULL, 'mobile', 'Chrome', 'Linux', 0, '2026-03-11 06:07:15', '2026-03-11 06:07:15', '2026-03-11 06:07:15'),
(87, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 'http://attakadom.test/product/cable-management-systems-control-panel-boxes', NULL, 'SY', NULL, 'mobile', 'Chrome', 'Linux', 0, '2026-03-11 06:07:58', '2026-03-11 06:07:58', '2026-03-11 06:07:58'),
(88, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 'http://attakadom.test', 'http://attakadom.test/product/cable-management-systems-control-panel-boxes', 'SY', NULL, 'mobile', 'Chrome', 'Linux', 0, '2026-03-11 06:08:26', '2026-03-11 06:08:26', '2026-03-11 06:08:26'),
(89, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 'http://attakadom.test/categories', 'http://attakadom.test/', 'SY', NULL, 'mobile', 'Chrome', 'Linux', 0, '2026-03-11 06:08:33', '2026-03-11 06:08:33', '2026-03-11 06:08:33'),
(90, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-12 18:26:53', '2026-03-12 18:26:53', '2026-03-12 18:26:53'),
(91, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-12 18:50:19', '2026-03-12 18:50:19', '2026-03-12 18:50:19'),
(92, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/admin', 'http://attakadom.test/login', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-12 18:52:16', '2026-03-12 18:52:16', '2026-03-12 18:52:16'),
(93, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-12 18:57:31', '2026-03-12 18:57:31', '2026-03-12 18:57:31'),
(94, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/category/gypsum-partitions-suspended-ceilings', 'http://attakadom.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-12 18:57:56', '2026-03-12 18:57:56', '2026-03-12 18:57:56'),
(95, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/product/gypsum-partitions-suspended-ceilings-threaded-rod-hanger', 'http://attakadom.test/category/gypsum-partitions-suspended-ceilings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-12 18:58:06', '2026-03-12 18:58:06', '2026-03-12 18:58:06'),
(96, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/product/gypsum-partitions-suspended-ceilings-c-bracket', 'http://attakadom.test/product/gypsum-partitions-suspended-ceilings-threaded-rod-hanger', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-12 19:00:22', '2026-03-12 19:00:22', '2026-03-12 19:00:22'),
(97, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/product/gypsum-partitions-suspended-ceilings-c-bracket', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-12 19:00:30', '2026-03-12 19:00:30', '2026-03-12 19:00:30'),
(98, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-12 23:50:50', '2026-03-12 23:50:50', '2026-03-12 23:50:50'),
(99, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-12 23:52:49', '2026-03-12 23:52:49', '2026-03-12 23:52:49'),
(100, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-12 23:56:08', '2026-03-12 23:56:08', '2026-03-12 23:56:08'),
(101, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-12 23:56:34', '2026-03-12 23:56:34', '2026-03-12 23:56:34'),
(102, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-12 23:58:46', '2026-03-12 23:58:46', '2026-03-12 23:58:46'),
(103, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-12 23:58:52', '2026-03-12 23:58:52', '2026-03-12 23:58:52'),
(104, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-12 23:58:59', '2026-03-12 23:58:59', '2026-03-12 23:58:59'),
(105, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-13 00:00:08', '2026-03-13 00:00:08', '2026-03-13 00:00:08'),
(106, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-13 00:00:18', '2026-03-13 00:00:18', '2026-03-13 00:00:18'),
(107, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-13 00:00:27', '2026-03-13 00:00:27', '2026-03-13 00:00:27'),
(108, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-13 00:01:49', '2026-03-13 00:01:49', '2026-03-13 00:01:49'),
(109, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-13 00:01:58', '2026-03-13 00:01:58', '2026-03-13 00:01:58'),
(110, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-13 00:02:07', '2026-03-13 00:02:07', '2026-03-13 00:02:07'),
(111, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-13 00:02:15', '2026-03-13 00:02:15', '2026-03-13 00:02:15'),
(112, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/category/pipe-clamps-hangers-fixings', 'http://attakadom.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-13 00:02:25', '2026-03-13 00:02:25', '2026-03-13 00:02:25'),
(113, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/product/pipe-clamps-hangers-fixings-pipe-hanger', 'http://attakadom.test/category/pipe-clamps-hangers-fixings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-13 00:02:40', '2026-03-13 00:02:40', '2026-03-13 00:02:40'),
(114, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/product/pipe-clamps-hangers-fixings-clevis-clamp', 'http://attakadom.test/product/pipe-clamps-hangers-fixings-pipe-hanger', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-13 00:02:52', '2026-03-13 00:02:52', '2026-03-13 00:02:52'),
(115, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', 'http://attakadom.test/product/pipe-clamps-hangers-fixings-clevis-clamp', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-13 00:03:04', '2026-03-13 00:03:04', '2026-03-13 00:03:04'),
(116, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/category/gypsum-partitions-suspended-ceilings', 'http://attakadom.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-13 00:03:16', '2026-03-13 00:03:16', '2026-03-13 00:03:16'),
(117, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/category/pipe-clamps-hangers-fixings', 'http://attakadom.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-13 00:03:40', '2026-03-13 00:03:40', '2026-03-13 00:03:40'),
(118, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/category/gypsum-partitions-suspended-ceilings', 'http://attakadom.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-13 00:03:47', '2026-03-13 00:03:47', '2026-03-13 00:03:47'),
(119, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/category/gypsum-partitions-suspended-ceilings?page=2', 'http://attakadom.test/category/gypsum-partitions-suspended-ceilings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-13 00:03:53', '2026-03-13 00:03:53', '2026-03-13 00:03:53'),
(120, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', 'http://attakadom.test/category/gypsum-partitions-suspended-ceilings?page=2', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-13 00:04:04', '2026-03-13 00:04:04', '2026-03-13 00:04:04'),
(121, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/api/search?q=%D9%82%D8%B6', 'http://attakadom.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-13 00:04:28', '2026-03-13 00:04:28', '2026-03-13 00:04:28'),
(122, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/api/search?q=%D9%82%D8%B6%D9%8A', 'http://attakadom.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-13 00:04:29', '2026-03-13 00:04:29', '2026-03-13 00:04:29'),
(123, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/api/search?q=%D9%82%D8%B6%D9%8A%D8%A8', 'http://attakadom.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-13 00:04:31', '2026-03-13 00:04:31', '2026-03-13 00:04:31'),
(124, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/product/cladding-facade-accessories-threaded-rod', 'http://attakadom.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-13 00:04:38', '2026-03-13 00:04:38', '2026-03-13 00:04:38'),
(125, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/api/search?q=%D8%AD%D8%A7', 'http://attakadom.test/product/cladding-facade-accessories-threaded-rod', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-13 00:04:52', '2026-03-13 00:04:52', '2026-03-13 00:04:52'),
(126, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/api/search?q=%D8%AD%D8%A7%D9%85%D9%84', 'http://attakadom.test/product/cladding-facade-accessories-threaded-rod', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-13 00:04:52', '2026-03-13 00:04:52', '2026-03-13 00:04:52'),
(127, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/product/gypsum-partitions-suspended-ceilings-threaded-rod-hanger', 'http://attakadom.test/product/cladding-facade-accessories-threaded-rod', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-13 00:04:56', '2026-03-13 00:04:56', '2026-03-13 00:04:56'),
(128, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 17:41:17', '2026-03-14 17:41:17', '2026-03-14 17:41:17'),
(129, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 17:41:23', '2026-03-14 17:41:23', '2026-03-14 17:41:23'),
(130, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 17:41:57', '2026-03-14 17:41:57', '2026-03-14 17:41:57'),
(131, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 17:43:52', '2026-03-14 17:43:52', '2026-03-14 17:43:52'),
(132, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 17:43:59', '2026-03-14 17:43:59', '2026-03-14 17:43:59'),
(133, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/category/architectural-building-solutions', 'http://attakadom.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 17:47:41', '2026-03-14 17:47:41', '2026-03-14 17:47:41'),
(134, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/product/architectural-building-solutions-rubber-corner-guard-impact-protection', 'http://attakadom.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 17:48:19', '2026-03-14 17:48:19', '2026-03-14 17:48:19'),
(135, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/categories', 'http://attakadom.test/product/architectural-building-solutions-rubber-corner-guard-impact-protection', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 17:49:12', '2026-03-14 17:49:12', '2026-03-14 17:49:12'),
(136, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/category/consumable-items-hardware', 'http://attakadom.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 17:49:33', '2026-03-14 17:49:33', '2026-03-14 17:49:33'),
(137, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/featured-products', 'http://attakadom.test/category/consumable-items-hardware', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 17:51:55', '2026-03-14 17:51:55', '2026-03-14 17:51:55'),
(138, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/api/search?q=%D9%85%D8%B3', 'http://attakadom.test/featured-products', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 17:53:18', '2026-03-14 17:53:18', '2026-03-14 17:53:18'),
(139, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/api/search?q=%D9%85%D8%B3%D8%A7', 'http://attakadom.test/featured-products', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 17:53:25', '2026-03-14 17:53:25', '2026-03-14 17:53:25'),
(140, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/api/search?q=%D9%85%D8%B3%D8%A7%D9%85', 'http://attakadom.test/featured-products', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 17:53:26', '2026-03-14 17:53:26', '2026-03-14 17:53:26'),
(141, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/product/consumable-items-hardware-concrete-nails', 'http://attakadom.test/featured-products', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 17:53:30', '2026-03-14 17:53:30', '2026-03-14 17:53:30'),
(142, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 17:55:17', '2026-03-14 17:55:17', '2026-03-14 17:55:17'),
(143, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/admin', 'http://attakadom.test/login', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 17:55:38', '2026-03-14 17:55:38', '2026-03-14 17:55:38'),
(144, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test', 'http://attakadom.test/product/consumable-items-hardware-concrete-nails', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 17:58:58', '2026-03-14 17:58:58', '2026-03-14 17:58:58'),
(145, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/category/pipe-clamps-hangers-fixings', 'http://attakadom.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 17:59:19', '2026-03-14 17:59:19', '2026-03-14 17:59:19'),
(146, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/categories', 'http://attakadom.test/category/pipe-clamps-hangers-fixings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 18:02:11', '2026-03-14 18:02:11', '2026-03-14 18:02:11'),
(147, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/category/architectural-building-solutions', 'http://attakadom.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 18:02:14', '2026-03-14 18:02:14', '2026-03-14 18:02:14'),
(148, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/category/architectural-building-solutions?page=2', 'http://attakadom.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 18:02:26', '2026-03-14 18:02:26', '2026-03-14 18:02:26'),
(149, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://attakadom.test/product/architectural-building-solutions-garbage-linen-chutes', 'http://attakadom.test/category/architectural-building-solutions?page=2', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 18:03:01', '2026-03-14 18:03:01', '2026-03-14 18:03:01'),
(150, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://localhost/awan/public', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 19:00:12', '2026-03-14 19:00:12', '2026-03-14 19:00:12'),
(151, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', 'http://localhost/awan/public', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 19:01:27', '2026-03-14 19:01:27', '2026-03-14 19:01:27'),
(152, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://localhost/awan/public', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 19:02:16', '2026-03-14 19:02:16', '2026-03-14 19:02:16'),
(153, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://localhost/awan/public', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 19:04:11', '2026-03-14 19:04:11', '2026-03-14 19:04:11'),
(154, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://localhost/awan/public', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 19:04:26', '2026-03-14 19:04:26', '2026-03-14 19:04:26'),
(155, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://localhost/awan/public', 'http://localhost/awan/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 19:10:42', '2026-03-14 19:10:42', '2026-03-14 19:10:42'),
(156, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://localhost/awan/public', 'http://localhost/awan/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 19:11:02', '2026-03-14 19:11:02', '2026-03-14 19:11:02');
INSERT INTO `visitors` (`id`, `ip_address`, `user_agent`, `page_url`, `referrer`, `country`, `city`, `device_type`, `browser`, `os`, `is_bot`, `visited_at`, `created_at`, `updated_at`) VALUES
(157, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://localhost/awan/public', 'http://localhost/awan/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 19:11:02', '2026-03-14 19:11:02', '2026-03-14 19:11:02'),
(158, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://localhost/awan/public', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 19:13:54', '2026-03-14 19:13:54', '2026-03-14 19:13:54'),
(159, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Herd/1.26.0 Chrome/120.0.6099.291 Electron/28.2.5 Safari/537.36', 'http://awan.test/?herd=preview', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 20:04:44', '2026-03-14 20:04:44', '2026-03-14 20:04:44'),
(160, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 20:04:44', '2026-03-14 20:04:44', '2026-03-14 20:04:44'),
(161, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 20:04:48', '2026-03-14 20:04:48', '2026-03-14 20:04:48'),
(162, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://awan.test/admin', 'http://awan.test/login', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 20:06:14', '2026-03-14 20:06:14', '2026-03-14 20:06:14'),
(163, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 20:08:19', '2026-03-14 20:08:19', '2026-03-14 20:08:19'),
(164, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://awan.test/category/cable-management-systems', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 20:08:26', '2026-03-14 20:08:26', '2026-03-14 20:08:26'),
(165, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://awan.test/vision', 'http://awan.test/category/cable-management-systems', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 20:08:31', '2026-03-14 20:08:31', '2026-03-14 20:08:31'),
(166, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://awan.test/contact', 'http://awan.test/vision', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 20:08:37', '2026-03-14 20:08:37', '2026-03-14 20:08:37'),
(167, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/contact', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 20:08:43', '2026-03-14 20:08:43', '2026-03-14 20:08:43'),
(168, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 20:15:30', '2026-03-14 20:15:30', '2026-03-14 20:15:30'),
(169, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://awan.test/admin', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 20:15:30', '2026-03-14 20:15:30', '2026-03-14 20:15:30'),
(170, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 20:15:39', '2026-03-14 20:15:39', '2026-03-14 20:15:39'),
(171, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 20:16:09', '2026-03-14 20:16:09', '2026-03-14 20:16:09'),
(172, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://awan.test/admin', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 20:16:13', '2026-03-14 20:16:13', '2026-03-14 20:16:13'),
(173, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 20:16:35', '2026-03-14 20:16:35', '2026-03-14 20:16:35'),
(174, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://awan.test/featured-products', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 20:16:37', '2026-03-14 20:16:37', '2026-03-14 20:16:37'),
(175, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://awan.test/vision', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 20:16:38', '2026-03-14 20:16:38', '2026-03-14 20:16:38'),
(176, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 20:17:33', '2026-03-14 20:17:33', '2026-03-14 20:17:33'),
(177, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://awan.test/category/blockwork-plastering-accessories', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 20:17:49', '2026-03-14 20:17:49', '2026-03-14 20:17:49'),
(178, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/blockwork-plastering-accessories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 20:18:06', '2026-03-14 20:18:06', '2026-03-14 20:18:06'),
(179, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://awan.test/category/gypsum-partitions-suspended-ceilings', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 20:18:22', '2026-03-14 20:18:22', '2026-03-14 20:18:22'),
(180, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://awan.test/product/gypsum-partitions-suspended-ceilings-g-corner-tape-corner-tape', 'http://awan.test/category/gypsum-partitions-suspended-ceilings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 20:18:26', '2026-03-14 20:18:26', '2026-03-14 20:18:26'),
(181, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://awan.test/product/gypsum-partitions-suspended-ceilings-g-corner-tape-corner-tape', 'http://awan.test/category/gypsum-partitions-suspended-ceilings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 20:18:27', '2026-03-14 20:18:27', '2026-03-14 20:18:27'),
(182, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://awan.test/admin', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 20:19:24', '2026-03-14 20:19:24', '2026-03-14 20:19:24'),
(183, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 'http://awan.test', 'http://awan.test/product/gypsum-partitions-suspended-ceilings-g-corner-tape-corner-tape', 'SY', NULL, 'mobile', 'Chrome', 'Linux', 0, '2026-03-14 20:19:42', '2026-03-14 20:19:42', '2026-03-14 20:19:42'),
(184, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 'http://awan.test/category/waterproofing-thermal-insulation', 'http://awan.test/', 'SY', NULL, 'mobile', 'Chrome', 'Linux', 0, '2026-03-14 20:19:54', '2026-03-14 20:19:54', '2026-03-14 20:19:54'),
(185, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 'http://awan.test', 'http://awan.test/category/waterproofing-thermal-insulation', 'SY', NULL, 'mobile', 'Chrome', 'Linux', 0, '2026-03-14 20:20:06', '2026-03-14 20:20:06', '2026-03-14 20:20:06'),
(186, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 'http://awan.test/featured-products', 'http://awan.test/', 'SY', NULL, 'mobile', 'Chrome', 'Linux', 0, '2026-03-14 20:20:15', '2026-03-14 20:20:15', '2026-03-14 20:20:15'),
(187, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/featured-products', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 20:37:47', '2026-03-14 20:37:47', '2026-03-14 20:37:47'),
(188, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 20:38:15', '2026-03-14 20:38:15', '2026-03-14 20:38:15'),
(189, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://awan.test/category/cable-management-systems', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 20:38:20', '2026-03-14 20:38:20', '2026-03-14 20:38:20'),
(190, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/cable-management-systems', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 20:38:33', '2026-03-14 20:38:33', '2026-03-14 20:38:33'),
(191, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://awan.test/category/concrete-formwork-accessories', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 20:38:36', '2026-03-14 20:38:36', '2026-03-14 20:38:36'),
(192, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://awan.test/product/concrete-formwork-accessories-pvc-coil', 'http://awan.test/category/concrete-formwork-accessories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-14 20:38:59', '2026-03-14 20:38:59', '2026-03-14 20:38:59'),
(193, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-15 19:07:07', '2026-03-15 19:07:07', '2026-03-15 19:07:07'),
(194, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-15 20:02:28', '2026-03-15 20:02:28', '2026-03-15 20:02:28'),
(195, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-15 20:02:47', '2026-03-15 20:02:47', '2026-03-15 20:02:47'),
(196, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-rubber-wall-guard-impact-protection', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-15 20:04:35', '2026-03-15 20:04:35', '2026-03-15 20:04:35'),
(197, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-steel-bollards', 'http://awan.test/product/architectural-building-solutions-rubber-wall-guard-impact-protection', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-15 20:04:52', '2026-03-15 20:04:52', '2026-03-15 20:04:52'),
(198, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-27 16:44:38', '2026-03-27 16:44:38', '2026-03-27 16:44:38'),
(199, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/admin', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-27 16:44:38', '2026-03-27 16:44:38', '2026-03-27 16:44:38'),
(200, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/admin', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-27 16:44:47', '2026-03-27 16:44:47', '2026-03-27 16:44:47'),
(201, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-27 16:44:54', '2026-03-27 16:44:54', '2026-03-27 16:44:54'),
(202, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-27 16:45:09', '2026-03-27 16:45:09', '2026-03-27 16:45:09'),
(203, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-steel-bollards', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-27 16:45:45', '2026-03-27 16:45:45', '2026-03-27 16:45:45'),
(204, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/product/architectural-building-solutions-steel-bollards', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-27 16:45:56', '2026-03-27 16:45:56', '2026-03-27 16:45:56'),
(205, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-27 16:46:17', '2026-03-27 16:46:17', '2026-03-27 16:46:17'),
(206, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/featured-products', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-27 16:46:22', '2026-03-27 16:46:22', '2026-03-27 16:46:22'),
(207, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/vision', 'http://awan.test/featured-products', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-27 16:46:30', '2026-03-27 16:46:30', '2026-03-27 16:46:30'),
(208, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/about', 'http://awan.test/vision', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-27 16:47:14', '2026-03-27 16:47:14', '2026-03-27 16:47:14'),
(209, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/vision', 'http://awan.test/about', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-27 16:48:05', '2026-03-27 16:48:05', '2026-03-27 16:48:05'),
(210, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/contact', 'http://awan.test/vision', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-27 16:48:11', '2026-03-27 16:48:11', '2026-03-27 16:48:11'),
(211, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/contact', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-27 16:48:17', '2026-03-27 16:48:17', '2026-03-27 16:48:17'),
(212, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-27 16:48:21', '2026-03-27 16:48:21', '2026-03-27 16:48:21'),
(213, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions?page=2', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-27 16:49:02', '2026-03-27 16:49:02', '2026-03-27 16:49:02'),
(214, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-dock-fender-d-fender', 'http://awan.test/category/architectural-building-solutions?page=2', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-27 16:50:22', '2026-03-27 16:50:22', '2026-03-27 16:50:22'),
(215, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/product/architectural-building-solutions-dock-fender-d-fender', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-27 16:50:31', '2026-03-27 16:50:31', '2026-03-27 16:50:31'),
(216, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/cable-management-systems', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-27 16:50:34', '2026-03-27 16:50:34', '2026-03-27 16:50:34'),
(217, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/cable-management-systems', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-27 16:51:01', '2026-03-27 16:51:01', '2026-03-27 16:51:01'),
(218, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/blockwork-plastering-accessories', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-27 16:51:04', '2026-03-27 16:51:04', '2026-03-27 16:51:04'),
(219, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/blockwork-plastering-accessories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-27 16:55:28', '2026-03-27 16:55:28', '2026-03-27 16:55:28'),
(220, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-27 16:55:29', '2026-03-27 16:55:29', '2026-03-27 16:55:29'),
(221, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-27 16:55:35', '2026-03-27 16:55:35', '2026-03-27 16:55:35'),
(222, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-27 16:55:44', '2026-03-27 16:55:44', '2026-03-27 16:55:44'),
(223, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/gypsum-partitions-suspended-ceilings', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-27 16:55:49', '2026-03-27 16:55:49', '2026-03-27 16:55:49'),
(224, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/gypsum-partitions-suspended-ceilings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-27 17:11:44', '2026-03-27 17:11:44', '2026-03-27 17:11:44'),
(225, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/concrete-formwork-accessories', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-27 17:11:48', '2026-03-27 17:11:48', '2026-03-27 17:11:48'),
(226, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/concrete-formwork-accessories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-27 17:11:56', '2026-03-27 17:11:56', '2026-03-27 17:11:56'),
(227, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/waterproofing-thermal-insulation', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-27 17:12:04', '2026-03-27 17:12:04', '2026-03-27 17:12:04'),
(228, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/waterproofing-thermal-insulation', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-27 17:13:19', '2026-03-27 17:13:19', '2026-03-27 17:13:19'),
(229, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/pipe-clamps-hangers-fixings', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-27 17:13:37', '2026-03-27 17:13:37', '2026-03-27 17:13:37'),
(230, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/pipe-clamps-hangers-fixings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-27 17:16:24', '2026-03-27 17:16:24', '2026-03-27 17:16:24'),
(231, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/gypsum-partitions-suspended-ceilings', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-27 17:16:31', '2026-03-27 17:16:31', '2026-03-27 17:16:31'),
(232, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/gypsum-partitions-suspended-ceilings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-27 17:16:42', '2026-03-27 17:16:42', '2026-03-27 17:16:42'),
(233, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/cladding-facade-accessories', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-27 17:16:46', '2026-03-27 17:16:46', '2026-03-27 17:16:46'),
(234, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/cladding-facade-accessories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-27 17:16:57', '2026-03-27 17:16:57', '2026-03-27 17:16:57'),
(235, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/consumable-items-hardware', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-27 17:17:00', '2026-03-27 17:17:00', '2026-03-27 17:17:00'),
(236, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-28 14:42:42', '2026-03-28 14:42:42', '2026-03-28 14:42:42'),
(237, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-28 14:44:08', '2026-03-28 14:44:08', '2026-03-28 14:44:08'),
(238, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/vision', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-28 14:44:21', '2026-03-28 14:44:21', '2026-03-28 14:44:21'),
(239, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/about', 'http://awan.test/vision', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-28 14:44:26', '2026-03-28 14:44:26', '2026-03-28 14:44:26'),
(240, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/about', 'http://awan.test/about', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-28 14:44:27', '2026-03-28 14:44:27', '2026-03-28 14:44:27'),
(241, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/contact', 'http://awan.test/about', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-28 14:44:39', '2026-03-28 14:44:39', '2026-03-28 14:44:39'),
(242, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/contact', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-28 14:44:50', '2026-03-28 14:44:50', '2026-03-28 14:44:50'),
(243, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-28 14:45:00', '2026-03-28 14:45:00', '2026-03-28 14:45:00'),
(244, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-28 14:45:07', '2026-03-28 14:45:07', '2026-03-28 14:45:07'),
(245, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions?page=2', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-28 14:45:34', '2026-03-28 14:45:34', '2026-03-28 14:45:34'),
(246, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/architectural-building-solutions?page=2', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-28 14:45:44', '2026-03-28 14:45:44', '2026-03-28 14:45:44'),
(247, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/cable-management-systems', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-28 14:45:48', '2026-03-28 14:45:48', '2026-03-28 14:45:48'),
(248, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/cable-management-systems', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-28 14:46:04', '2026-03-28 14:46:04', '2026-03-28 14:46:04'),
(249, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/blockwork-plastering-accessories', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-28 14:46:07', '2026-03-28 14:46:07', '2026-03-28 14:46:07'),
(250, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/blockwork-plastering-accessories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-28 14:46:24', '2026-03-28 14:46:24', '2026-03-28 14:46:24'),
(251, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/concrete-formwork-accessories', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-28 14:46:28', '2026-03-28 14:46:28', '2026-03-28 14:46:28'),
(252, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/concrete-formwork-accessories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-28 14:47:12', '2026-03-28 14:47:12', '2026-03-28 14:47:12'),
(253, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/waterproofing-thermal-insulation', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-28 14:47:16', '2026-03-28 14:47:16', '2026-03-28 14:47:16'),
(254, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/waterproofing-thermal-insulation-pvc-waterstop', 'http://awan.test/category/waterproofing-thermal-insulation', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-28 14:47:43', '2026-03-28 14:47:43', '2026-03-28 14:47:43'),
(255, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/product/waterproofing-thermal-insulation-pvc-waterstop', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-28 14:48:10', '2026-03-28 14:48:10', '2026-03-28 14:48:10'),
(256, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/gypsum-partitions-suspended-ceilings', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-28 14:48:15', '2026-03-28 14:48:15', '2026-03-28 14:48:15'),
(257, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/gypsum-partitions-suspended-ceilings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-28 14:50:01', '2026-03-28 14:50:01', '2026-03-28 14:50:01'),
(258, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/consumable-items-hardware', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-28 14:50:07', '2026-03-28 14:50:07', '2026-03-28 14:50:07'),
(259, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/consumable-items-hardware', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-28 14:51:00', '2026-03-28 14:51:00', '2026-03-28 14:51:00'),
(260, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/cladding-facade-accessories', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-28 14:51:05', '2026-03-28 14:51:05', '2026-03-28 14:51:05'),
(261, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/admin', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-28 14:52:27', '2026-03-28 14:52:27', '2026-03-28 14:52:27'),
(262, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/admin', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-29 16:17:34', '2026-03-29 16:17:34', '2026-03-29 16:17:34'),
(263, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/admin', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-29 16:17:34', '2026-03-29 16:17:34', '2026-03-29 16:17:34'),
(264, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/admin', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-29 17:21:23', '2026-03-29 17:21:23', '2026-03-29 17:21:23'),
(265, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-29 17:22:36', '2026-03-29 17:22:36', '2026-03-29 17:22:36'),
(266, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-29 17:22:41', '2026-03-29 17:22:41', '2026-03-29 17:22:41'),
(267, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-29 17:22:51', '2026-03-29 17:22:51', '2026-03-29 17:22:51'),
(268, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-29 17:23:09', '2026-03-29 17:23:09', '2026-03-29 17:23:09'),
(269, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-29 17:38:36', '2026-03-29 17:38:36', '2026-03-29 17:38:36'),
(270, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-29 17:46:43', '2026-03-29 17:46:43', '2026-03-29 17:46:43'),
(271, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-29 17:49:33', '2026-03-29 17:49:33', '2026-03-29 17:49:33'),
(272, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions?page=2', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-29 17:51:30', '2026-03-29 17:51:30', '2026-03-29 17:51:30'),
(273, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/architectural-building-solutions?page=2', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-29 17:52:15', '2026-03-29 17:52:15', '2026-03-29 17:52:15'),
(274, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/cable-management-systems', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-29 17:52:18', '2026-03-29 17:52:18', '2026-03-29 17:52:18'),
(275, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-29 17:56:25', '2026-03-29 17:56:25', '2026-03-29 17:56:25'),
(276, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/cable-management-systems', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-29 17:56:31', '2026-03-29 17:56:31', '2026-03-29 17:56:31'),
(277, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/cable-management-systems-cable-trunking', 'http://awan.test/category/cable-management-systems', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-29 17:56:37', '2026-03-29 17:56:37', '2026-03-29 17:56:37'),
(278, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/cable-management-systems-cable-tray', 'http://awan.test/product/cable-management-systems-cable-trunking', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-29 17:56:45', '2026-03-29 17:56:45', '2026-03-29 17:56:45'),
(279, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/cable-management-systems', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-29 17:56:54', '2026-03-29 17:56:54', '2026-03-29 17:56:54'),
(280, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/cable-management-systems-cable-tray', 'http://awan.test/category/cable-management-systems', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-29 17:56:59', '2026-03-29 17:56:59', '2026-03-29 17:56:59'),
(281, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/cable-management-systems-cable-tray', 'http://awan.test/category/cable-management-systems', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-03-29 17:59:37', '2026-03-29 17:59:37', '2026-03-29 17:59:37'),
(282, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-02 14:42:42', '2026-04-02 14:42:42', '2026-04-02 14:42:42'),
(283, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-02 14:43:23', '2026-04-02 14:43:23', '2026-04-02 14:43:23'),
(284, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-02 14:43:43', '2026-04-02 14:43:43', '2026-04-02 14:43:43'),
(285, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-10 23:44:59', '2026-04-10 23:44:59', '2026-04-10 23:44:59'),
(286, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-10 23:45:34', '2026-04-10 23:45:34', '2026-04-10 23:45:34'),
(287, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/admin', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-10 23:45:39', '2026-04-10 23:45:39', '2026-04-10 23:45:39'),
(288, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-10 23:45:50', '2026-04-10 23:45:50', '2026-04-10 23:45:50'),
(289, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-10 23:46:03', '2026-04-10 23:46:03', '2026-04-10 23:46:03'),
(290, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/admin', 'http://awan.test/admin/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-10 23:46:48', '2026-04-10 23:46:48', '2026-04-10 23:46:48'),
(291, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-10 23:47:08', '2026-04-10 23:47:08', '2026-04-10 23:47:08'),
(292, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/gypsum-partitions-suspended-ceilings', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-10 23:47:19', '2026-04-10 23:47:19', '2026-04-10 23:47:19'),
(293, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/gypsum-partitions-suspended-ceilings', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-10 23:47:19', '2026-04-10 23:47:19', '2026-04-10 23:47:19'),
(294, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/gypsum-partitions-suspended-ceilings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-10 23:47:29', '2026-04-10 23:47:29', '2026-04-10 23:47:29'),
(295, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-10 23:47:32', '2026-04-10 23:47:32', '2026-04-10 23:47:32'),
(296, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-steel-bollards', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-10 23:48:28', '2026-04-10 23:48:28', '2026-04-10 23:48:28'),
(297, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/product/architectural-building-solutions-steel-bollards', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-10 23:48:49', '2026-04-10 23:48:49', '2026-04-10 23:48:49'),
(298, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-10 23:48:52', '2026-04-10 23:48:52', '2026-04-10 23:48:52'),
(299, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-fencing', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-10 23:49:18', '2026-04-10 23:49:18', '2026-04-10 23:49:18'),
(300, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/product/architectural-building-solutions-fencing', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-10 23:49:45', '2026-04-10 23:49:45', '2026-04-10 23:49:45'),
(301, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-10 23:49:48', '2026-04-10 23:49:48', '2026-04-10 23:49:48'),
(302, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-steel-bollards', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-10 23:49:52', '2026-04-10 23:49:52', '2026-04-10 23:49:52'),
(303, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/product/architectural-building-solutions-steel-bollards', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-10 23:53:41', '2026-04-10 23:53:41', '2026-04-10 23:53:41'),
(304, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-10 23:53:48', '2026-04-10 23:53:48', '2026-04-10 23:53:48'),
(305, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-11 00:06:42', '2026-04-11 00:06:42', '2026-04-11 00:06:42'),
(306, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-steel-bollards', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-11 00:06:46', '2026-04-11 00:06:46', '2026-04-11 00:06:46'),
(307, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/product/architectural-building-solutions-steel-bollards', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-11 00:34:09', '2026-04-11 00:34:09', '2026-04-11 00:34:09'),
(308, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/consumable-items-hardware', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-11 00:34:27', '2026-04-11 00:34:27', '2026-04-11 00:34:27'),
(309, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/consumable-items-hardware-common-nails', 'http://awan.test/category/consumable-items-hardware', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-11 00:34:31', '2026-04-11 00:34:31', '2026-04-11 00:34:31');
INSERT INTO `visitors` (`id`, `ip_address`, `user_agent`, `page_url`, `referrer`, `country`, `city`, `device_type`, `browser`, `os`, `is_bot`, `visited_at`, `created_at`, `updated_at`) VALUES
(310, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/product/consumable-items-hardware-common-nails', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-11 00:39:08', '2026-04-11 00:39:08', '2026-04-11 00:39:08'),
(311, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-11 00:39:19', '2026-04-11 00:39:19', '2026-04-11 00:39:19'),
(312, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-steel-bollards', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-11 00:39:23', '2026-04-11 00:39:23', '2026-04-11 00:39:23'),
(313, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-steel-bollards', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-11 00:42:26', '2026-04-11 00:42:26', '2026-04-11 00:42:26'),
(314, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-steel-bollards', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-11 00:44:09', '2026-04-11 00:44:09', '2026-04-11 00:44:09'),
(315, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/product/architectural-building-solutions-steel-bollards', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-11 00:44:11', '2026-04-11 00:44:11', '2026-04-11 00:44:11'),
(316, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/pipe-clamps-hangers-fixings', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-11 00:44:14', '2026-04-11 00:44:14', '2026-04-11 00:44:14'),
(317, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/pipe-clamps-hangers-fixings-pipe-clamp', 'http://awan.test/category/pipe-clamps-hangers-fixings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-11 00:44:19', '2026-04-11 00:44:20', '2026-04-11 00:44:20'),
(318, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/product/pipe-clamps-hangers-fixings-pipe-clamp', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-11 00:44:25', '2026-04-11 00:44:25', '2026-04-11 00:44:25'),
(319, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-11 00:44:32', '2026-04-11 00:44:32', '2026-04-11 00:44:32'),
(320, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-steel-bollards', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-11 00:44:35', '2026-04-11 00:44:35', '2026-04-11 00:44:35'),
(321, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-steel-bollards', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-11 00:47:44', '2026-04-11 00:47:44', '2026-04-11 00:47:44'),
(322, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/product/architectural-building-solutions-steel-bollards', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-11 00:47:47', '2026-04-11 00:47:47', '2026-04-11 00:47:47'),
(323, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/blockwork-plastering-accessories', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-11 00:48:01', '2026-04-11 00:48:01', '2026-04-11 00:48:01'),
(324, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/blockwork-plastering-accessories-lintel-block-ties', 'http://awan.test/category/blockwork-plastering-accessories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-11 00:48:06', '2026-04-11 00:48:06', '2026-04-11 00:48:06'),
(325, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/blockwork-plastering-accessories-lintel-block-ties', 'http://awan.test/category/blockwork-plastering-accessories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-11 00:48:57', '2026-04-11 00:48:57', '2026-04-11 00:48:57'),
(326, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/product/blockwork-plastering-accessories-lintel-block-ties', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-11 00:53:17', '2026-04-11 00:53:17', '2026-04-11 00:53:17'),
(327, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/product/blockwork-plastering-accessories-lintel-block-ties', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-11 00:53:24', '2026-04-11 00:53:24', '2026-04-11 00:53:24'),
(328, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-11 00:53:41', '2026-04-11 00:53:41', '2026-04-11 00:53:41'),
(329, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/api/search?q=%D9%82%D8%B9', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-11 00:53:57', '2026-04-11 00:53:57', '2026-04-11 00:53:57'),
(330, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/api/search?q=%D9%82%D8%B9%D9%84%D8%A7%D9%84%D8%A7%D8%AB%D9%82', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-11 00:53:58', '2026-04-11 00:53:58', '2026-04-11 00:53:58'),
(331, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/api/search?q=%D9%82%D8%B9%D9%84%D8%A7%D9%84%D8%A7%D8%AB%D9%82', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-11 00:53:58', '2026-04-11 00:53:58', '2026-04-11 00:53:58'),
(332, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/api/search?q=ru', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-11 00:54:03', '2026-04-11 00:54:03', '2026-04-11 00:54:03'),
(333, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/api/search?q=rubber', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-11 00:54:04', '2026-04-11 00:54:04', '2026-04-11 00:54:04'),
(334, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/api/search?q=rubber%20we', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-11 00:54:08', '2026-04-11 00:54:08', '2026-04-11 00:54:08'),
(335, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/api/search?q=rubber%20w', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-11 00:54:08', '2026-04-11 00:54:08', '2026-04-11 00:54:08'),
(336, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/api/search?q=rubber%20wh', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-11 00:54:08', '2026-04-11 00:54:08', '2026-04-11 00:54:08'),
(337, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/api/search?q=rubber%20whee', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-11 00:54:09', '2026-04-11 00:54:09', '2026-04-11 00:54:09'),
(338, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/api/search?q=rubber%20wheel', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-11 00:54:10', '2026-04-11 00:54:10', '2026-04-11 00:54:10'),
(339, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-rubber-wheel-stopper', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-11 00:54:12', '2026-04-11 00:54:12', '2026-04-11 00:54:12'),
(340, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-rubber-wheel-stopper', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-11 00:59:34', '2026-04-11 00:59:34', '2026-04-11 00:59:34'),
(341, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/product/architectural-building-solutions-rubber-wheel-stopper', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-11 00:59:41', '2026-04-11 00:59:41', '2026-04-11 00:59:41'),
(342, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/api/search?q=%D8%B4%D8%A8', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-11 00:59:57', '2026-04-11 00:59:57', '2026-04-11 00:59:57'),
(343, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/api/search?q=%D8%B4%D8%A8%D9%83', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-11 00:59:58', '2026-04-11 00:59:58', '2026-04-11 00:59:58'),
(344, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/blockwork-plastering-accessories-corner-mesh', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-11 01:00:01', '2026-04-11 01:00:01', '2026-04-11 01:00:01'),
(345, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/admin', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-12 17:31:21', '2026-04-12 17:31:21', '2026-04-12 17:31:21'),
(346, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-12 17:33:55', '2026-04-12 17:33:55', '2026-04-12 17:33:55'),
(347, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/admin', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-12 17:33:57', '2026-04-12 17:33:57', '2026-04-12 17:33:57'),
(348, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-12 17:36:31', '2026-04-12 17:36:31', '2026-04-12 17:36:31'),
(349, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-12 18:34:03', '2026-04-12 18:34:03', '2026-04-12 18:34:03'),
(350, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/gypsum-partitions-suspended-ceilings-c-bracket', 'http://awan.test/admin/products?page=3', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-12 18:41:27', '2026-04-12 18:41:27', '2026-04-12 18:41:27'),
(351, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/gypsum-partitions-suspended-ceilings-rod-clip', 'http://awan.test/admin/products?page=3', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-12 18:43:37', '2026-04-12 18:43:37', '2026-04-12 18:43:37'),
(352, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/waterproofing-thermal-insulation-liquid-membrane', 'http://awan.test/admin/products?page=3', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-12 18:48:54', '2026-04-12 18:48:54', '2026-04-12 18:48:54'),
(353, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-12 19:07:50', '2026-04-12 19:07:50', '2026-04-12 19:07:50'),
(354, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-12 19:07:54', '2026-04-12 19:07:54', '2026-04-12 19:07:54'),
(355, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/admin', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-12 19:09:45', '2026-04-12 19:09:45', '2026-04-12 19:09:45'),
(356, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/admin', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-12 19:22:49', '2026-04-12 19:22:49', '2026-04-12 19:22:49'),
(357, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/admin', 'http://awan.test/admin/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-12 19:25:28', '2026-04-12 19:25:28', '2026-04-12 19:25:28'),
(358, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 13:20:46', '2026-04-14 13:20:46', '2026-04-14 13:20:46'),
(359, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/admin', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 13:20:54', '2026-04-14 13:20:54', '2026-04-14 13:20:54'),
(360, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 13:20:59', '2026-04-14 13:20:59', '2026-04-14 13:20:59'),
(361, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-steel-bollards', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 13:21:06', '2026-04-14 13:21:06', '2026-04-14 13:21:06'),
(362, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/product/architectural-building-solutions-steel-bollards', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 13:21:30', '2026-04-14 13:21:30', '2026-04-14 13:21:30'),
(363, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 13:21:34', '2026-04-14 13:21:34', '2026-04-14 13:21:34'),
(364, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-entrance-mats', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 13:21:38', '2026-04-14 13:21:38', '2026-04-14 13:21:38'),
(365, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-cubicle-toilet-partitions', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 13:21:49', '2026-04-14 13:21:49', '2026-04-14 13:21:49'),
(366, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-tile-carpet-trim', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 13:21:57', '2026-04-14 13:21:57', '2026-04-14 13:21:57'),
(367, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-wall-guard-impact-protection', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 13:22:03', '2026-04-14 13:22:03', '2026-04-14 13:22:03'),
(368, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/product/architectural-building-solutions-wall-guard-impact-protection', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 13:22:09', '2026-04-14 13:22:09', '2026-04-14 13:22:09'),
(369, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/cable-management-systems', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 13:22:11', '2026-04-14 13:22:11', '2026-04-14 13:22:11'),
(370, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/cable-management-systems-cable-trunking', 'http://awan.test/category/cable-management-systems', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 13:22:16', '2026-04-14 13:22:16', '2026-04-14 13:22:16'),
(371, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/product/cable-management-systems-cable-trunking', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 13:22:26', '2026-04-14 13:22:26', '2026-04-14 13:22:26'),
(372, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/cable-management-systems', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 13:22:29', '2026-04-14 13:22:29', '2026-04-14 13:22:29'),
(373, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/cable-management-systems-cable-ladder-basket-tray', 'http://awan.test/category/cable-management-systems', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 13:22:34', '2026-04-14 13:22:34', '2026-04-14 13:22:34'),
(374, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/product/cable-management-systems-cable-ladder-basket-tray', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 13:22:44', '2026-04-14 13:22:44', '2026-04-14 13:22:44'),
(375, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/waterproofing-thermal-insulation', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 13:22:46', '2026-04-14 13:22:46', '2026-04-14 13:22:46'),
(376, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/waterproofing-thermal-insulation-bitumen-membrane', 'http://awan.test/category/waterproofing-thermal-insulation', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 13:22:50', '2026-04-14 13:22:50', '2026-04-14 13:22:50'),
(377, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/product/waterproofing-thermal-insulation-bitumen-membrane', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 13:22:56', '2026-04-14 13:22:56', '2026-04-14 13:22:56'),
(378, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/cladding-facade-accessories', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 13:23:02', '2026-04-14 13:23:02', '2026-04-14 13:23:02'),
(379, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/cladding-facade-accessories-spring-nut', 'http://awan.test/category/cladding-facade-accessories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 13:23:06', '2026-04-14 13:23:06', '2026-04-14 13:23:06'),
(380, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/cladding-facade-accessories-spring-nut', 'http://awan.test/category/cladding-facade-accessories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 13:23:12', '2026-04-14 13:23:12', '2026-04-14 13:23:12'),
(381, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/product/cladding-facade-accessories-spring-nut', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 13:23:24', '2026-04-14 13:23:24', '2026-04-14 13:23:24'),
(382, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/consumable-items-hardware', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 13:23:28', '2026-04-14 13:23:28', '2026-04-14 13:23:28'),
(383, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/consumable-items-hardware-hand-tools', 'http://awan.test/category/consumable-items-hardware', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 13:23:31', '2026-04-14 13:23:31', '2026-04-14 13:23:31'),
(384, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/product/consumable-items-hardware-hand-tools', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 13:27:35', '2026-04-14 13:27:35', '2026-04-14 13:27:35'),
(385, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-steel-bollards', 'http://awan.test/admin/products?search=%D8%AD%D9%88%D8%A7%D8%AC%D8%B2', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 13:29:39', '2026-04-14 13:29:39', '2026-04-14 13:29:39'),
(386, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/product/consumable-items-hardware-hand-tools', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 13:29:54', '2026-04-14 13:29:54', '2026-04-14 13:29:54'),
(387, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-steel-bollards', 'http://awan.test/admin/products?search=%D8%AD%D9%88%D8%A7%D8%AC%D8%B2', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 13:33:54', '2026-04-14 13:33:54', '2026-04-14 13:33:54'),
(388, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/product/architectural-building-solutions-steel-bollards', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 13:34:51', '2026-04-14 13:34:51', '2026-04-14 13:34:51'),
(389, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 13:34:58', '2026-04-14 13:34:58', '2026-04-14 13:34:58'),
(390, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-steel-bollards', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 13:35:33', '2026-04-14 13:35:33', '2026-04-14 13:35:33'),
(391, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-fencing', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 13:35:37', '2026-04-14 13:35:37', '2026-04-14 13:35:37'),
(392, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-metal-gates', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 13:35:44', '2026-04-14 13:35:44', '2026-04-14 13:35:44'),
(393, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/product/architectural-building-solutions-metal-gates', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 13:54:47', '2026-04-14 13:54:47', '2026-04-14 13:54:47'),
(394, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/blockwork-plastering-accessories', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 14:15:36', '2026-04-14 14:15:36', '2026-04-14 14:15:36'),
(395, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 14:17:09', '2026-04-14 14:17:09', '2026-04-14 14:17:09'),
(396, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 14:17:16', '2026-04-14 14:17:16', '2026-04-14 14:17:16'),
(397, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 14:17:24', '2026-04-14 14:17:24', '2026-04-14 14:17:24'),
(398, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 14:17:27', '2026-04-14 14:17:27', '2026-04-14 14:17:27'),
(399, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 14:17:36', '2026-04-14 14:17:36', '2026-04-14 14:17:36'),
(400, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 14:17:55', '2026-04-14 14:17:55', '2026-04-14 14:17:55'),
(401, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 14:17:59', '2026-04-14 14:17:59', '2026-04-14 14:17:59'),
(402, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 14:18:04', '2026-04-14 14:18:04', '2026-04-14 14:18:04'),
(403, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 14:18:09', '2026-04-14 14:18:09', '2026-04-14 14:18:09'),
(404, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 14:19:35', '2026-04-14 14:19:35', '2026-04-14 14:19:35'),
(405, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/blockwork-plastering-accessories', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 14:19:43', '2026-04-14 14:19:43', '2026-04-14 14:19:43'),
(406, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/blockwork-plastering-accessories-corner-bead', 'http://awan.test/admin/products?search=%D8%B7%D9%8A%D9%86%D8%A9', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 14:22:55', '2026-04-14 14:22:55', '2026-04-14 14:22:55'),
(407, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/blockwork-plastering-accessories-corner-mesh', 'http://awan.test/admin/products?search=%D8%B7%D9%8A%D9%86%D8%A9', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 14:23:07', '2026-04-14 14:23:07', '2026-04-14 14:23:07'),
(408, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/blockwork-plastering-accessories', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 14:28:03', '2026-04-14 14:28:03', '2026-04-14 14:28:03'),
(409, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/api/search?q=%D8%B7%D9%8A', 'http://awan.test/category/blockwork-plastering-accessories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 14:28:06', '2026-04-14 14:28:06', '2026-04-14 14:28:06'),
(410, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/api/search?q=%D8%B7%D9%8A%D9%86%D8%A9', 'http://awan.test/category/blockwork-plastering-accessories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 14:28:07', '2026-04-14 14:28:07', '2026-04-14 14:28:07'),
(411, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/blockwork-plastering-accessories-corner-bead', 'http://awan.test/category/blockwork-plastering-accessories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 14:28:14', '2026-04-14 14:28:14', '2026-04-14 14:28:14'),
(412, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/product/blockwork-plastering-accessories-corner-mesh', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 14:35:34', '2026-04-14 14:35:34', '2026-04-14 14:35:34'),
(413, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/cladding-facade-accessories', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 15:07:41', '2026-04-14 15:07:41', '2026-04-14 15:07:41'),
(414, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/cladding-facade-accessories-unistrut-channel', 'http://awan.test/category/cladding-facade-accessories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 15:07:47', '2026-04-14 15:07:47', '2026-04-14 15:07:47'),
(415, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/threaded-rod', 'http://awan.test/admin/products', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 15:07:58', '2026-04-14 15:07:58', '2026-04-14 15:07:58'),
(416, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/product/threaded-rod', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 15:46:36', '2026-04-14 15:46:36', '2026-04-14 15:46:36'),
(417, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/product/threaded-rod', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 16:28:28', '2026-04-14 16:28:28', '2026-04-14 16:28:28'),
(418, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/product/threaded-rod', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 16:28:32', '2026-04-14 16:28:32', '2026-04-14 16:28:32'),
(419, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/product/threaded-rod', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 16:28:37', '2026-04-14 16:28:37', '2026-04-14 16:28:37'),
(420, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/gypsum-partitions-suspended-ceilings', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 16:28:50', '2026-04-14 16:28:50', '2026-04-14 16:28:50'),
(421, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/gypsum-partitions-suspended-ceilings-threaded-rod-hanger', 'http://awan.test/category/gypsum-partitions-suspended-ceilings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 16:28:53', '2026-04-14 16:28:53', '2026-04-14 16:28:53'),
(422, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/gypsum-partitions-suspended-ceilings-drop-in-anchor', 'http://awan.test/category/gypsum-partitions-suspended-ceilings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 16:29:06', '2026-04-14 16:29:06', '2026-04-14 16:29:06'),
(423, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/gypsum-partitions-suspended-ceilings-g-corner-tape-corner-tape', 'http://awan.test/category/gypsum-partitions-suspended-ceilings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 16:29:13', '2026-04-14 16:29:13', '2026-04-14 16:29:13'),
(424, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/gypsum-partitions-suspended-ceilings-gypsum-board', 'http://awan.test/category/gypsum-partitions-suspended-ceilings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 16:29:33', '2026-04-14 16:29:33', '2026-04-14 16:29:33'),
(425, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/gypsum-partitions-suspended-ceilings-cement-board', 'http://awan.test/category/gypsum-partitions-suspended-ceilings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 16:29:46', '2026-04-14 16:29:46', '2026-04-14 16:29:46'),
(426, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/gypsum-partitions-suspended-ceilings-stud-runner', 'http://awan.test/category/gypsum-partitions-suspended-ceilings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-14 16:29:59', '2026-04-14 16:29:59', '2026-04-14 16:29:59'),
(427, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/product/gypsum-partitions-suspended-ceilings-stud-runner', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 11:40:44', '2026-04-16 11:40:44', '2026-04-16 11:40:44'),
(428, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/featured-products', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 11:42:03', '2026-04-16 11:42:03', '2026-04-16 11:42:03'),
(429, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/vision', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 11:42:04', '2026-04-16 11:42:04', '2026-04-16 11:42:04'),
(430, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/featured-products', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 11:42:05', '2026-04-16 11:42:05', '2026-04-16 11:42:05'),
(431, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 11:42:06', '2026-04-16 11:42:06', '2026-04-16 11:42:06'),
(432, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 11:42:06', '2026-04-16 11:42:06', '2026-04-16 11:42:06'),
(433, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 12:01:54', '2026-04-16 12:01:54', '2026-04-16 12:01:54'),
(434, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/admin', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 12:02:14', '2026-04-16 12:02:14', '2026-04-16 12:02:14'),
(435, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/concrete-formwork-accessories-wing-nuts', 'http://awan.test/admin/products?page=5', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 12:05:36', '2026-04-16 12:05:36', '2026-04-16 12:05:36'),
(436, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/concrete-formwork-accessories-tie-rods', 'http://awan.test/admin/products?page=5', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 12:05:42', '2026-04-16 12:05:42', '2026-04-16 12:05:42'),
(437, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/blockwork-plastering-accessories-coil-lath', 'http://awan.test/admin/products?page=5', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 12:05:54', '2026-04-16 12:05:54', '2026-04-16 12:05:54'),
(438, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/blockwork-plastering-accessories-corner-mesh', 'http://awan.test/admin/products?page=5', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 12:06:02', '2026-04-16 12:06:02', '2026-04-16 12:06:02'),
(439, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/blockwork-plastering-accessories-corner-bead', 'http://awan.test/admin/products?page=4', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 12:06:45', '2026-04-16 12:06:45', '2026-04-16 12:06:45'),
(440, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/blockwork-plastering-accessories-lintel-block-ties', 'http://awan.test/admin/products?page=4', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 12:06:59', '2026-04-16 12:06:59', '2026-04-16 12:06:59'),
(441, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/waterproofing-thermal-insulation-hammer-anchor', 'http://awan.test/admin/products?page=4', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 12:07:12', '2026-04-16 12:07:12', '2026-04-16 12:07:12'),
(442, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/cable-management-systems-cable-ladder-basket-tray', 'http://awan.test/admin/products?page=4', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 12:07:26', '2026-04-16 12:07:26', '2026-04-16 12:07:26'),
(443, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/consumable-items-hardware-burlap-hessian-cloth', 'http://awan.test/admin/products', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 12:10:16', '2026-04-16 12:10:16', '2026-04-16 12:10:16'),
(444, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/consumable-items-hardware-tie-wire', 'http://awan.test/admin/products', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 12:10:22', '2026-04-16 12:10:22', '2026-04-16 12:10:22'),
(445, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/product/consumable-items-hardware-tie-wire', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 12:10:27', '2026-04-16 12:10:27', '2026-04-16 12:10:27'),
(446, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/pipe-clamps-hangers-fixings-u-clamp', 'http://awan.test/admin/products?page=2', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 12:10:47', '2026-04-16 12:10:47', '2026-04-16 12:10:47'),
(447, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/concrete-formwork-accessories-pvc-coil', 'http://awan.test/admin/products?page=3', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 12:11:07', '2026-04-16 12:11:07', '2026-04-16 12:11:07'),
(448, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/concrete-formwork-accessories-rapid-clamp', 'http://awan.test/admin/products?page=3', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 12:11:17', '2026-04-16 12:11:17', '2026-04-16 12:11:17'),
(449, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/waterproofing-thermal-insulation-polyethylene-flashing', 'http://awan.test/admin/products?page=4', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 12:11:37', '2026-04-16 12:11:37', '2026-04-16 12:11:37'),
(450, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/waterproofing-thermal-insulation-drill-bits', 'http://awan.test/admin/products?page=4', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 12:11:49', '2026-04-16 12:11:49', '2026-04-16 12:11:49'),
(451, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/waterproofing-thermal-insulation-expanded-polystyrene-eps', 'http://awan.test/admin/products?page=4', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 12:12:08', '2026-04-16 12:12:08', '2026-04-16 12:12:08');
INSERT INTO `visitors` (`id`, `ip_address`, `user_agent`, `page_url`, `referrer`, `country`, `city`, `device_type`, `browser`, `os`, `is_bot`, `visited_at`, `created_at`, `updated_at`) VALUES
(452, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/waterproofing-thermal-insulation-rock-wool', 'http://awan.test/admin/products?page=4', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 12:12:17', '2026-04-16 12:12:17', '2026-04-16 12:12:17'),
(453, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 12:31:05', '2026-04-16 12:31:05', '2026-04-16 12:31:05'),
(454, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/cable-management-systems', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 12:31:12', '2026-04-16 12:31:12', '2026-04-16 12:31:12'),
(455, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/admin', 'http://awan.test/admin/products', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 13:24:44', '2026-04-16 13:24:44', '2026-04-16 13:24:44'),
(456, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/admin', 'http://awan.test/admin/categories/architectural-building-solutions/edit', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 13:24:57', '2026-04-16 13:24:57', '2026-04-16 13:24:57'),
(457, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/cable-management-systems', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 13:26:32', '2026-04-16 13:26:32', '2026-04-16 13:26:32'),
(458, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/cable-management-systems-upvc-conduits', 'http://awan.test/category/cable-management-systems', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 13:26:44', '2026-04-16 13:26:44', '2026-04-16 13:26:44'),
(459, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/product/cable-management-systems-upvc-conduits', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 13:26:59', '2026-04-16 13:26:59', '2026-04-16 13:26:59'),
(460, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/blockwork-plastering-accessories-block-reinforcement-ladder-mesh', 'http://awan.test/admin/products?page=4', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 13:36:19', '2026-04-16 13:36:19', '2026-04-16 13:36:19'),
(461, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/blockwork-plastering-accessories-corner-bead', 'http://awan.test/admin/products?page=4', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 13:36:26', '2026-04-16 13:36:26', '2026-04-16 13:36:26'),
(462, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/blockwork-plastering-accessories-corner-bead', 'http://awan.test/admin/products?page=4', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 13:38:32', '2026-04-16 13:38:32', '2026-04-16 13:38:32'),
(463, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-wall-guard-impact-protection', 'http://awan.test/admin/products?page=6', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 15:45:56', '2026-04-16 15:45:56', '2026-04-16 15:45:56'),
(464, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-speed-humps', 'http://awan.test/admin/products?page=6', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 15:58:26', '2026-04-16 15:58:26', '2026-04-16 15:58:26'),
(465, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/concrete-formwork-accessories-plywood-plywood-timber', 'http://awan.test/admin/products?page=5', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 16:33:38', '2026-04-16 16:33:38', '2026-04-16 16:33:38'),
(466, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/product/architectural-building-solutions-speed-humps', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 16:33:51', '2026-04-16 16:33:51', '2026-04-16 16:33:51'),
(467, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/product/concrete-formwork-accessories-plywood-plywood-timber', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 16:49:54', '2026-04-16 16:49:54', '2026-04-16 16:49:54'),
(468, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/consumable-items-hardware', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 16:50:08', '2026-04-16 16:50:08', '2026-04-16 16:50:08'),
(469, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/consumable-items-hardware-common-nails', 'http://awan.test/category/consumable-items-hardware', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 16:50:12', '2026-04-16 16:50:12', '2026-04-16 16:50:12'),
(470, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/consumable-items-hardware-common-nails', 'http://awan.test/category/consumable-items-hardware', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 16:50:38', '2026-04-16 16:50:38', '2026-04-16 16:50:38'),
(471, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/product/consumable-items-hardware-common-nails', 'http://awan.test/category/consumable-items-hardware', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 16:50:40', '2026-04-16 16:50:40', '2026-04-16 16:50:40'),
(472, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/product/consumable-items-hardware-common-nails', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 16:50:41', '2026-04-16 16:50:41', '2026-04-16 16:50:41'),
(473, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/featured-products', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 16:50:48', '2026-04-16 16:50:48', '2026-04-16 16:50:48'),
(474, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/featured-products', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 16:50:49', '2026-04-16 16:50:49', '2026-04-16 16:50:49'),
(475, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 16:50:51', '2026-04-16 16:50:51', '2026-04-16 16:50:51'),
(476, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/contact', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 16:51:02', '2026-04-16 16:51:02', '2026-04-16 16:51:02'),
(477, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/contact', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 16:51:03', '2026-04-16 16:51:03', '2026-04-16 16:51:03'),
(478, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'http://awan.test/admin', 'http://awan.test/admin/settings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-16 17:12:29', '2026-04-16 17:12:29', '2026-04-16 17:12:29'),
(479, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-17 02:58:42', '2026-04-17 02:58:42', '2026-04-17 02:58:42'),
(480, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/gypsum-partitions-suspended-ceilings', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-17 02:58:55', '2026-04-17 02:58:55', '2026-04-17 02:58:55'),
(481, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/gypsum-partitions-suspended-ceilings', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-17 02:58:56', '2026-04-17 02:58:56', '2026-04-17 02:58:56'),
(482, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/gypsum-partitions-suspended-ceilings-g-corner-tape-corner-tape', 'http://awan.test/category/gypsum-partitions-suspended-ceilings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-17 02:59:01', '2026-04-17 02:59:01', '2026-04-17 02:59:01'),
(483, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/gypsum-partitions-suspended-ceilings-drop-in-anchor', 'http://awan.test/product/gypsum-partitions-suspended-ceilings-g-corner-tape-corner-tape', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-17 02:59:13', '2026-04-17 02:59:13', '2026-04-17 02:59:13'),
(484, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/gypsum-partitions-suspended-ceilings-channel-clamp', 'http://awan.test/product/gypsum-partitions-suspended-ceilings-drop-in-anchor', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-17 02:59:23', '2026-04-17 02:59:23', '2026-04-17 02:59:23'),
(485, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-17 03:01:16', '2026-04-17 03:01:16', '2026-04-17 03:01:16'),
(486, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/admin', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:17:43', '2026-04-18 14:17:43', '2026-04-18 14:17:43'),
(487, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/admin', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:17:55', '2026-04-18 14:17:55', '2026-04-18 14:17:55'),
(488, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:18:01', '2026-04-18 14:18:01', '2026-04-18 14:18:01'),
(489, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-corner-guard-impact-protection', 'http://awan.test/admin/products?page=6', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:18:35', '2026-04-18 14:18:35', '2026-04-18 14:18:35'),
(490, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-wall-guard-impact-protection', 'http://awan.test/admin/products?page=6', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:18:54', '2026-04-18 14:18:54', '2026-04-18 14:18:54'),
(491, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-wall-guard-impact-protection', 'http://awan.test/admin/products?page=6', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:19:07', '2026-04-18 14:19:07', '2026-04-18 14:19:07'),
(492, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-roof-hatch', 'http://awan.test/admin/products?page=6', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:19:15', '2026-04-18 14:19:15', '2026-04-18 14:19:15'),
(493, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-speed-humps', 'http://awan.test/admin/products?page=6', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:19:36', '2026-04-18 14:19:36', '2026-04-18 14:19:36'),
(494, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-corner-guard-impact-protection', 'http://awan.test/admin/products?page=6', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:20:15', '2026-04-18 14:20:15', '2026-04-18 14:20:15'),
(495, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-rubber-wheel-stopper', 'http://awan.test/admin/products?page=6', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:20:58', '2026-04-18 14:20:58', '2026-04-18 14:20:58'),
(496, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-rubber-wall-guard-impact-protection', 'http://awan.test/admin/products?page=6', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:21:10', '2026-04-18 14:21:10', '2026-04-18 14:21:10'),
(497, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-rubber-wheel-stopper', 'http://awan.test/admin/products?page=6', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:21:44', '2026-04-18 14:21:44', '2026-04-18 14:21:44'),
(498, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-dock-fender-d-fender', 'http://awan.test/admin/products?page=6', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:26:19', '2026-04-18 14:26:19', '2026-04-18 14:26:19'),
(499, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-entrance-mats', 'http://awan.test/admin/products?page=5', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:32:32', '2026-04-18 14:32:32', '2026-04-18 14:32:32'),
(500, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-metal-gates', 'http://awan.test/admin/products?page=5', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:32:45', '2026-04-18 14:32:45', '2026-04-18 14:32:45'),
(501, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/concrete-formwork-accessories-wing-nuts', 'http://awan.test/admin/products?page=5', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:33:09', '2026-04-18 14:33:09', '2026-04-18 14:33:09'),
(502, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/blockwork-plastering-accessories-coil-lath', 'http://awan.test/admin/products?page=5', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:33:16', '2026-04-18 14:33:16', '2026-04-18 14:33:16'),
(503, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/blockwork-plastering-accessories-corner-mesh', 'http://awan.test/admin/products?page=5', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:33:24', '2026-04-18 14:33:24', '2026-04-18 14:33:24'),
(504, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/waterproofing-thermal-insulation-drill-bits', 'http://awan.test/admin/products?page=4', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:33:53', '2026-04-18 14:33:53', '2026-04-18 14:33:53'),
(505, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/cable-management-systems-cable-tray', 'http://awan.test/admin/products?page=4', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:34:02', '2026-04-18 14:34:02', '2026-04-18 14:34:02'),
(506, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/cable-management-systems-cable-trunking', 'http://awan.test/admin/products?page=4', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:34:06', '2026-04-18 14:34:06', '2026-04-18 14:34:06'),
(507, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/cable-management-systems-control-panel-boxes', 'http://awan.test/admin/products?page=4', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:34:11', '2026-04-18 14:34:11', '2026-04-18 14:34:11'),
(508, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/cable-management-systems-upvc-conduits', 'http://awan.test/admin/products?page=4', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:34:17', '2026-04-18 14:34:17', '2026-04-18 14:34:17'),
(509, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/blockwork-plastering-accessories-block-reinforcement-ladder-mesh', 'http://awan.test/admin/products?page=4', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:34:23', '2026-04-18 14:34:23', '2026-04-18 14:34:23'),
(510, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/blockwork-plastering-accessories-corner-bead', 'http://awan.test/admin/products?page=4', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:34:28', '2026-04-18 14:34:28', '2026-04-18 14:34:28'),
(511, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-access-raised-floor', 'http://awan.test/admin/products?page=6', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:35:00', '2026-04-18 14:35:00', '2026-04-18 14:35:00'),
(512, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-garbage-linen-chutes', 'http://awan.test/admin/products?page=6', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:35:05', '2026-04-18 14:35:05', '2026-04-18 14:35:05'),
(513, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-handrail-systems', 'http://awan.test/admin/products?page=6', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:35:11', '2026-04-18 14:35:11', '2026-04-18 14:35:11'),
(514, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-rubber-wheel-stopper', 'http://awan.test/admin/products?page=6', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:35:19', '2026-04-18 14:35:19', '2026-04-18 14:35:19'),
(515, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-dock-fender-d-fender', 'http://awan.test/admin/products?page=6', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:35:36', '2026-04-18 14:35:36', '2026-04-18 14:35:36'),
(516, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/admin', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:35:58', '2026-04-18 14:35:58', '2026-04-18 14:35:58'),
(517, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:36:33', '2026-04-18 14:36:33', '2026-04-18 14:36:33'),
(518, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-lockers', 'http://awan.test/admin/products?page=7', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:38:28', '2026-04-18 14:38:28', '2026-04-18 14:38:28'),
(519, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-ladders', 'http://awan.test/admin/products?page=7', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:38:33', '2026-04-18 14:38:33', '2026-04-18 14:38:33'),
(520, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-gratings-steel-galvanized', 'http://awan.test/admin/products?page=7', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:38:38', '2026-04-18 14:38:38', '2026-04-18 14:38:38'),
(521, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-movement-joint-covers', 'http://awan.test/admin/products?page=6', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:38:54', '2026-04-18 14:38:54', '2026-04-18 14:38:54'),
(522, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-roof-hatch', 'http://awan.test/admin/products?page=6', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:39:00', '2026-04-18 14:39:00', '2026-04-18 14:39:00'),
(523, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-rubber-wheel-stopper', 'http://awan.test/admin/products?page=6', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:39:09', '2026-04-18 14:39:09', '2026-04-18 14:39:09'),
(524, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-corner-guard-impact-protection', 'http://awan.test/admin/products?page=6', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:39:18', '2026-04-18 14:39:18', '2026-04-18 14:39:18'),
(525, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-rubber-wheel-stopper', 'http://awan.test/admin/products?page=6', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:39:31', '2026-04-18 14:39:31', '2026-04-18 14:39:31'),
(526, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-wall-guard-impact-protection', 'http://awan.test/admin/products?page=6', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:40:22', '2026-04-18 14:40:22', '2026-04-18 14:40:22'),
(527, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-cubicle-toilet-partitions', 'http://awan.test/admin/products?page=6', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:40:27', '2026-04-18 14:40:27', '2026-04-18 14:40:27'),
(528, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-expansion-joint-systems', 'http://awan.test/admin/products?page=6', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:40:31', '2026-04-18 14:40:31', '2026-04-18 14:40:31'),
(529, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-stair-nosing', 'http://awan.test/admin/products?page=6', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:40:34', '2026-04-18 14:40:34', '2026-04-18 14:40:34'),
(530, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-corner-guard-impact-protection', 'http://awan.test/admin/products?page=6', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:46:03', '2026-04-18 14:46:03', '2026-04-18 14:46:03'),
(531, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-movement-joint-covers', 'http://awan.test/admin/products?page=6', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:53:54', '2026-04-18 14:53:54', '2026-04-18 14:53:54'),
(532, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-dock-fender-d-fender', 'http://awan.test/admin/products?page=6', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:54:05', '2026-04-18 14:54:05', '2026-04-18 14:54:05'),
(533, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/product/architectural-building-solutions-movement-joint-covers', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:54:45', '2026-04-18 14:54:45', '2026-04-18 14:54:45'),
(534, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-tile-carpet-trim', 'http://awan.test/admin/products?page=6', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 14:58:13', '2026-04-18 14:58:13', '2026-04-18 14:58:13'),
(535, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/blockwork-plastering-accessories-expanded-metal-lath', 'http://awan.test/admin/products?page=5', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 15:01:04', '2026-04-18 15:01:04', '2026-04-18 15:01:04'),
(536, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-entrance-mats', 'http://awan.test/admin/products?page=5', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 15:01:48', '2026-04-18 15:01:48', '2026-04-18 15:01:48'),
(537, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/product/blockwork-plastering-accessories-expanded-metal-lath', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 15:09:10', '2026-04-18 15:09:10', '2026-04-18 15:09:10'),
(538, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/gypsum-partitions-suspended-ceilings-adjustable-spring-clip', 'http://awan.test/admin/products?page=3', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 16:36:22', '2026-04-18 16:36:22', '2026-04-18 16:36:22'),
(539, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/concrete-formwork-accessories-rib-washers', 'http://awan.test/admin/products?page=3', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 16:36:35', '2026-04-18 16:36:35', '2026-04-18 16:36:35'),
(540, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/concrete-formwork-accessories-pvc-pipe', 'http://awan.test/admin/products?page=3', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 16:36:40', '2026-04-18 16:36:40', '2026-04-18 16:36:40'),
(541, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/concrete-formwork-accessories-c-clamp', 'http://awan.test/admin/products?page=3', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 17:51:26', '2026-04-18 17:51:26', '2026-04-18 17:51:26'),
(542, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/waterproofing-thermal-insulation-bitumen-membrane', 'http://awan.test/admin/products?page=3', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 17:51:39', '2026-04-18 17:51:39', '2026-04-18 17:51:39'),
(543, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/waterproofing-thermal-insulation-geotextile-fabric', 'http://awan.test/admin/products?page=3', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 17:53:31', '2026-04-18 17:53:31', '2026-04-18 17:53:31'),
(544, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/gypsum-partitions-suspended-ceilings-drop-in-anchor', 'http://awan.test/admin/products?page=1', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 17:53:47', '2026-04-18 17:53:47', '2026-04-18 17:53:47'),
(545, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/gypsum-partitions-suspended-ceilings-g-corner-tape-corner-tape', 'http://awan.test/admin/products?page=1', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 17:53:54', '2026-04-18 17:53:54', '2026-04-18 17:53:54'),
(546, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/threaded-rod', 'http://awan.test/admin/products?page=1', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 17:54:00', '2026-04-18 17:54:00', '2026-04-18 17:54:00'),
(547, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-access-raised-floor', 'http://awan.test/admin/products?page=6', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:12:18', '2026-04-18 21:12:18', '2026-04-18 21:12:18'),
(548, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-entrance-mats', 'http://awan.test/admin/products?page=5', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:12:33', '2026-04-18 21:12:33', '2026-04-18 21:12:33'),
(549, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-metal-gates', 'http://awan.test/admin/products?page=5', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:12:43', '2026-04-18 21:12:43', '2026-04-18 21:12:43'),
(550, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-fencing', 'http://awan.test/admin/products?page=5', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:13:23', '2026-04-18 21:13:23', '2026-04-18 21:13:23'),
(551, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-steel-bollards', 'http://awan.test/admin/products?page=5', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:13:34', '2026-04-18 21:13:34', '2026-04-18 21:13:34'),
(552, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/concrete-formwork-accessories-tie-rods', 'http://awan.test/admin/products?page=5', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:16:29', '2026-04-18 21:16:29', '2026-04-18 21:16:29'),
(553, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/concrete-formwork-accessories-tie-rods', 'http://awan.test/admin/products?page=5', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:20:52', '2026-04-18 21:20:52', '2026-04-18 21:20:52'),
(554, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-steel-bollards', 'http://awan.test/admin/products?page=5', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:20:59', '2026-04-18 21:20:59', '2026-04-18 21:20:59'),
(555, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/blockwork-plastering-accessories-control-joint', 'http://awan.test/admin/products?page=5', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:24:29', '2026-04-18 21:24:29', '2026-04-18 21:24:29'),
(556, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/blockwork-plastering-accessories-architrave-bead', 'http://awan.test/admin/products?page=5', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:24:35', '2026-04-18 21:24:35', '2026-04-18 21:24:35'),
(557, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/blockwork-plastering-accessories-expanded-metal-lath', 'http://awan.test/admin/products?page=5', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:24:45', '2026-04-18 21:24:45', '2026-04-18 21:24:45'),
(558, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/blockwork-plastering-accessories-coil-lath', 'http://awan.test/admin/products?page=5', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:25:05', '2026-04-18 21:25:05', '2026-04-18 21:25:05'),
(559, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/blockwork-plastering-accessories-rib-lath', 'http://awan.test/admin/products?page=5', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:25:18', '2026-04-18 21:25:18', '2026-04-18 21:25:18'),
(560, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-steel-bollards', 'http://awan.test/admin/products?page=5', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:25:28', '2026-04-18 21:25:28', '2026-04-18 21:25:28'),
(561, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-metal-gates', 'http://awan.test/admin/products?page=5', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:25:36', '2026-04-18 21:25:36', '2026-04-18 21:25:36'),
(562, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/consumable-items-hardware-tie-wire', 'http://awan.test/admin/products', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:38:42', '2026-04-18 21:38:42', '2026-04-18 21:38:42'),
(563, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/consumable-items-hardware-burlap-hessian-cloth', 'http://awan.test/admin/products', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:38:46', '2026-04-18 21:38:46', '2026-04-18 21:38:46'),
(564, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/consumable-items-hardware-concrete-nails', 'http://awan.test/admin/products', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:38:51', '2026-04-18 21:38:51', '2026-04-18 21:38:51'),
(565, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/consumable-items-hardware-hand-tools', 'http://awan.test/admin/products', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:39:00', '2026-04-18 21:39:00', '2026-04-18 21:39:00'),
(566, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/cladding-facade-accessories-through-bolt', 'http://awan.test/admin/products', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:39:20', '2026-04-18 21:39:20', '2026-04-18 21:39:20'),
(567, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/gypsum-partitions-suspended-ceilings-threaded-rod-hanger', 'http://awan.test/admin/products', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:39:36', '2026-04-18 21:39:36', '2026-04-18 21:39:36'),
(568, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/product/gypsum-partitions-suspended-ceilings-threaded-rod-hanger', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:39:56', '2026-04-18 21:39:56', '2026-04-18 21:39:56'),
(569, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:40:08', '2026-04-18 21:40:08', '2026-04-18 21:40:08'),
(570, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-steel-bollards', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:40:13', '2026-04-18 21:40:13', '2026-04-18 21:40:13'),
(571, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-handrail-systems', 'http://awan.test/product/architectural-building-solutions-steel-bollards', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:40:21', '2026-04-18 21:40:21', '2026-04-18 21:40:21'),
(572, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/product/architectural-building-solutions-handrail-systems', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:40:26', '2026-04-18 21:40:26', '2026-04-18 21:40:26'),
(573, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/gypsum-partitions-suspended-ceilings', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:40:29', '2026-04-18 21:40:29', '2026-04-18 21:40:29'),
(574, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/gypsum-partitions-suspended-ceilings-g-corner-tape-corner-tape', 'http://awan.test/category/gypsum-partitions-suspended-ceilings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:40:32', '2026-04-18 21:40:32', '2026-04-18 21:40:32'),
(575, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/product/gypsum-partitions-suspended-ceilings-g-corner-tape-corner-tape', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:40:42', '2026-04-18 21:40:42', '2026-04-18 21:40:42'),
(576, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:40:54', '2026-04-18 21:40:54', '2026-04-18 21:40:54'),
(577, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:41:18', '2026-04-18 21:41:18', '2026-04-18 21:41:18'),
(578, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/cable-management-systems', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:41:21', '2026-04-18 21:41:21', '2026-04-18 21:41:21'),
(579, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/category/cable-management-systems', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:41:29', '2026-04-18 21:41:29', '2026-04-18 21:41:29'),
(580, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/blockwork-plastering-accessories', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:41:36', '2026-04-18 21:41:36', '2026-04-18 21:41:36'),
(581, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/category/blockwork-plastering-accessories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:41:54', '2026-04-18 21:41:54', '2026-04-18 21:41:54'),
(582, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/concrete-formwork-accessories', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:42:00', '2026-04-18 21:42:00', '2026-04-18 21:42:00'),
(583, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/concrete-formwork-accessories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:42:35', '2026-04-18 21:42:35', '2026-04-18 21:42:35'),
(584, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/waterproofing-thermal-insulation', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:42:40', '2026-04-18 21:42:40', '2026-04-18 21:42:40'),
(585, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/waterproofing-thermal-insulation-protection-board', 'http://awan.test/category/waterproofing-thermal-insulation', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:42:51', '2026-04-18 21:42:51', '2026-04-18 21:42:51'),
(586, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/product/waterproofing-thermal-insulation-protection-board', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:42:59', '2026-04-18 21:42:59', '2026-04-18 21:42:59'),
(587, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/waterproofing-thermal-insulation', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:43:03', '2026-04-18 21:43:03', '2026-04-18 21:43:03'),
(588, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/waterproofing-thermal-insulation', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:43:26', '2026-04-18 21:43:26', '2026-04-18 21:43:26'),
(589, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/pipe-clamps-hangers-fixings', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:43:29', '2026-04-18 21:43:29', '2026-04-18 21:43:29'),
(590, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/pipe-clamps-hangers-fixings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:43:47', '2026-04-18 21:43:47', '2026-04-18 21:43:47'),
(591, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/gypsum-partitions-suspended-ceilings', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:43:51', '2026-04-18 21:43:51', '2026-04-18 21:43:51');
INSERT INTO `visitors` (`id`, `ip_address`, `user_agent`, `page_url`, `referrer`, `country`, `city`, `device_type`, `browser`, `os`, `is_bot`, `visited_at`, `created_at`, `updated_at`) VALUES
(592, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/gypsum-partitions-suspended-ceilings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:44:04', '2026-04-18 21:44:04', '2026-04-18 21:44:04'),
(593, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/cladding-facade-accessories', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:44:07', '2026-04-18 21:44:07', '2026-04-18 21:44:07'),
(594, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/cladding-facade-accessories-flat-anchor', 'http://awan.test/category/cladding-facade-accessories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:44:16', '2026-04-18 21:44:16', '2026-04-18 21:44:16'),
(595, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/product/consumable-items-hardware-hand-tools', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:44:43', '2026-04-18 21:44:43', '2026-04-18 21:44:43'),
(596, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/cladding-facade-accessories', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:44:49', '2026-04-18 21:44:49', '2026-04-18 21:44:49'),
(597, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/cladding-facade-accessories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:44:52', '2026-04-18 21:44:52', '2026-04-18 21:44:52'),
(598, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/consumable-items-hardware', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:44:55', '2026-04-18 21:44:55', '2026-04-18 21:44:55'),
(599, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/product/cladding-facade-accessories-through-bolt', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:46:27', '2026-04-18 21:46:27', '2026-04-18 21:46:27'),
(600, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:46:47', '2026-04-18 21:46:47', '2026-04-18 21:46:47'),
(601, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:48:47', '2026-04-18 21:48:47', '2026-04-18 21:48:47'),
(602, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/cable-management-systems', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:48:50', '2026-04-18 21:48:50', '2026-04-18 21:48:50'),
(603, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/cable-management-systems-cable-tray', 'http://awan.test/category/cable-management-systems', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:49:13', '2026-04-18 21:49:13', '2026-04-18 21:49:13'),
(604, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/cable-management-systems-cable-trunking', 'http://awan.test/product/cable-management-systems-cable-tray', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:49:18', '2026-04-18 21:49:18', '2026-04-18 21:49:18'),
(605, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/cable-management-systems-control-panel-boxes', 'http://awan.test/product/cable-management-systems-cable-trunking', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:49:22', '2026-04-18 21:49:22', '2026-04-18 21:49:22'),
(606, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/cable-management-systems-upvc-conduits', 'http://awan.test/product/cable-management-systems-control-panel-boxes', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:49:28', '2026-04-18 21:49:28', '2026-04-18 21:49:28'),
(607, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/product/cable-management-systems-upvc-conduits', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:49:32', '2026-04-18 21:49:32', '2026-04-18 21:49:32'),
(608, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/cable-management-systems', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:49:34', '2026-04-18 21:49:34', '2026-04-18 21:49:34'),
(609, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/cable-management-systems', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:50:15', '2026-04-18 21:50:15', '2026-04-18 21:50:15'),
(610, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/blockwork-plastering-accessories', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:50:18', '2026-04-18 21:50:18', '2026-04-18 21:50:18'),
(611, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/blockwork-plastering-accessories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:50:43', '2026-04-18 21:50:43', '2026-04-18 21:50:43'),
(612, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/concrete-formwork-accessories', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:50:49', '2026-04-18 21:50:49', '2026-04-18 21:50:49'),
(613, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/concrete-formwork-accessories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:52:33', '2026-04-18 21:52:33', '2026-04-18 21:52:33'),
(614, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:53:06', '2026-04-18 21:53:06', '2026-04-18 21:53:06'),
(615, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:53:09', '2026-04-18 21:53:09', '2026-04-18 21:53:09'),
(616, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions?page=2', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:53:17', '2026-04-18 21:53:17', '2026-04-18 21:53:17'),
(617, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/architectural-building-solutions?page=2', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:53:43', '2026-04-18 21:53:43', '2026-04-18 21:53:43'),
(618, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/blockwork-plastering-accessories', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:53:50', '2026-04-18 21:53:50', '2026-04-18 21:53:50'),
(619, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/blockwork-plastering-accessories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:53:54', '2026-04-18 21:53:54', '2026-04-18 21:53:54'),
(620, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/concrete-formwork-accessories', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:53:56', '2026-04-18 21:53:56', '2026-04-18 21:53:56'),
(621, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/consumable-items-hardware-tie-wire', 'http://awan.test/admin/products?search=%D8%AA%D8%B1%D8%A8%D9%8A%D8%B7', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:55:25', '2026-04-18 21:55:25', '2026-04-18 21:55:25'),
(622, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/concrete-formwork-accessories-binding-wire', 'http://awan.test/admin/products?search=%D8%AA%D8%B1%D8%A8%D9%8A%D8%B7', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:55:29', '2026-04-18 21:55:29', '2026-04-18 21:55:29'),
(623, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/product/consumable-items-hardware-tie-wire', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:57:09', '2026-04-18 21:57:09', '2026-04-18 21:57:09'),
(624, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/concrete-formwork-accessories', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:57:12', '2026-04-18 21:57:12', '2026-04-18 21:57:12'),
(625, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/concrete-formwork-accessories-rapid-clamp', 'http://awan.test/category/concrete-formwork-accessories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 21:57:23', '2026-04-18 21:57:23', '2026-04-18 21:57:23'),
(626, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/product/concrete-formwork-accessories-rapid-clamp', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:03:22', '2026-04-18 22:03:22', '2026-04-18 22:03:22'),
(627, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/concrete-formwork-accessories', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:04:01', '2026-04-18 22:04:01', '2026-04-18 22:04:01'),
(628, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/concrete-formwork-accessories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:04:11', '2026-04-18 22:04:11', '2026-04-18 22:04:11'),
(629, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/waterproofing-thermal-insulation', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:04:14', '2026-04-18 22:04:14', '2026-04-18 22:04:14'),
(630, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/waterproofing-thermal-insulation', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:05:53', '2026-04-18 22:05:53', '2026-04-18 22:05:53'),
(631, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/waterproofing-thermal-insulation', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:05:57', '2026-04-18 22:05:57', '2026-04-18 22:05:57'),
(632, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/pipe-clamps-hangers-fixings', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:06:01', '2026-04-18 22:06:01', '2026-04-18 22:06:01'),
(633, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/concrete-formwork-accessories-c-clamp', 'http://awan.test/category/pipe-clamps-hangers-fixings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:06:19', '2026-04-18 22:06:19', '2026-04-18 22:06:19'),
(634, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/pipe-clamps-hangers-fixings-strap-clamp', 'http://awan.test/category/pipe-clamps-hangers-fixings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:06:28', '2026-04-18 22:06:28', '2026-04-18 22:06:28'),
(635, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/pipe-clamps-hangers-fixings-bolt-clamp', 'http://awan.test/category/pipe-clamps-hangers-fixings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:07:09', '2026-04-18 22:07:09', '2026-04-18 22:07:09'),
(636, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/pipe-clamps-hangers-fixings-pipe-clamp', 'http://awan.test/category/pipe-clamps-hangers-fixings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:07:17', '2026-04-18 22:07:17', '2026-04-18 22:07:17'),
(637, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/pipe-clamps-hangers-fixings-swivel-clamp', 'http://awan.test/category/pipe-clamps-hangers-fixings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:07:22', '2026-04-18 22:07:22', '2026-04-18 22:07:22'),
(638, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/pipe-clamps-hangers-fixings-clevis-clamp', 'http://awan.test/category/pipe-clamps-hangers-fixings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:07:26', '2026-04-18 22:07:26', '2026-04-18 22:07:26'),
(639, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/pipe-clamps-hangers-fixings-channel', 'http://awan.test/category/pipe-clamps-hangers-fixings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:07:33', '2026-04-18 22:07:33', '2026-04-18 22:07:33'),
(640, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/pipe-clamps-hangers-fixings-beam-clamp', 'http://awan.test/category/pipe-clamps-hangers-fixings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:07:51', '2026-04-18 22:07:51', '2026-04-18 22:07:51'),
(641, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/pipe-clamps-hangers-fixings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:08:13', '2026-04-18 22:08:13', '2026-04-18 22:08:13'),
(642, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/gypsum-partitions-suspended-ceilings', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:08:17', '2026-04-18 22:08:17', '2026-04-18 22:08:17'),
(643, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/gypsum-partitions-suspended-ceilings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:09:10', '2026-04-18 22:09:10', '2026-04-18 22:09:10'),
(644, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/gypsum-partitions-suspended-ceilings', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:09:16', '2026-04-18 22:09:16', '2026-04-18 22:09:16'),
(645, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/cladding-facade-accessories', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:09:22', '2026-04-18 22:09:22', '2026-04-18 22:09:22'),
(646, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/cladding-facade-accessories-bracket', 'http://awan.test/category/cladding-facade-accessories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:09:26', '2026-04-18 22:09:26', '2026-04-18 22:09:26'),
(647, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/consumable-items-hardware', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:09:44', '2026-04-18 22:09:44', '2026-04-18 22:09:44'),
(648, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/concrete-formwork-accessories-pvc-coil', 'http://awan.test/category/consumable-items-hardware', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:09:50', '2026-04-18 22:09:50', '2026-04-18 22:09:50'),
(649, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/waterproofing-thermal-insulation-drill-bits', 'http://awan.test/category/consumable-items-hardware', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:09:57', '2026-04-18 22:09:57', '2026-04-18 22:09:57'),
(650, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/category/consumable-items-hardware', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:12:17', '2026-04-18 22:12:17', '2026-04-18 22:12:17'),
(651, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:12:39', '2026-04-18 22:12:39', '2026-04-18 22:12:39'),
(652, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-fencing', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:12:43', '2026-04-18 22:12:43', '2026-04-18 22:12:43'),
(653, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-metal-gates', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:12:52', '2026-04-18 22:12:52', '2026-04-18 22:12:52'),
(654, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-entrance-mats', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:12:56', '2026-04-18 22:12:56', '2026-04-18 22:12:56'),
(655, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-cubicle-toilet-partitions', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:12:59', '2026-04-18 22:12:59', '2026-04-18 22:12:59'),
(656, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-expansion-joint-systems', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:13:04', '2026-04-18 22:13:04', '2026-04-18 22:13:04'),
(657, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-stair-nosing', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:13:08', '2026-04-18 22:13:08', '2026-04-18 22:13:08'),
(658, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-tile-carpet-trim', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:13:13', '2026-04-18 22:13:13', '2026-04-18 22:13:13'),
(659, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-corner-guard-impact-protection', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:13:18', '2026-04-18 22:13:18', '2026-04-18 22:13:18'),
(660, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-wall-guard-impact-protection', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:13:21', '2026-04-18 22:13:21', '2026-04-18 22:13:21'),
(661, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-rubber-corner-guard-impact-protection', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:13:26', '2026-04-18 22:13:26', '2026-04-18 22:13:26'),
(662, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-rubber-wall-guard-impact-protection', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:13:32', '2026-04-18 22:13:32', '2026-04-18 22:13:32'),
(663, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-rubber-wall-guard-impact-protection', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:14:09', '2026-04-18 22:14:09', '2026-04-18 22:14:09'),
(664, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-rubber-corner-guard-impact-protection', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:14:16', '2026-04-18 22:14:16', '2026-04-18 22:14:16'),
(665, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-expansion-joint-systems', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:14:20', '2026-04-18 22:14:20', '2026-04-18 22:14:20'),
(666, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-wall-guard-impact-protection', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:14:23', '2026-04-18 22:14:23', '2026-04-18 22:14:23'),
(667, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-tile-carpet-trim', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:14:28', '2026-04-18 22:14:28', '2026-04-18 22:14:28'),
(668, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:15:21', '2026-04-18 22:15:21', '2026-04-18 22:15:21'),
(669, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-rubber-wall-guard-impact-protection', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:15:23', '2026-04-18 22:15:23', '2026-04-18 22:15:23'),
(670, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions?page=2', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:15:31', '2026-04-18 22:15:31', '2026-04-18 22:15:31'),
(671, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-dock-fender-d-fender', 'http://awan.test/category/architectural-building-solutions?page=2', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:15:34', '2026-04-18 22:15:34', '2026-04-18 22:15:34'),
(672, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-movement-joint-covers', 'http://awan.test/category/architectural-building-solutions?page=2', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:15:40', '2026-04-18 22:15:40', '2026-04-18 22:15:40'),
(673, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-roof-hatch', 'http://awan.test/category/architectural-building-solutions?page=2', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:15:44', '2026-04-18 22:15:44', '2026-04-18 22:15:44'),
(674, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-handrail-systems', 'http://awan.test/category/architectural-building-solutions?page=2', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:15:48', '2026-04-18 22:15:48', '2026-04-18 22:15:48'),
(675, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-garbage-linen-chutes', 'http://awan.test/category/architectural-building-solutions?page=2', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:15:51', '2026-04-18 22:15:51', '2026-04-18 22:15:51'),
(676, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-access-raised-floor', 'http://awan.test/category/architectural-building-solutions?page=2', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:15:56', '2026-04-18 22:15:56', '2026-04-18 22:15:56'),
(677, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-lockers', 'http://awan.test/category/architectural-building-solutions?page=2', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:16:00', '2026-04-18 22:16:00', '2026-04-18 22:16:00'),
(678, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-ladders', 'http://awan.test/category/architectural-building-solutions?page=2', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:16:03', '2026-04-18 22:16:03', '2026-04-18 22:16:03'),
(679, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-gratings-steel-galvanized', 'http://awan.test/category/architectural-building-solutions?page=2', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:16:06', '2026-04-18 22:16:06', '2026-04-18 22:16:06'),
(680, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/architectural-building-solutions?page=2', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:16:13', '2026-04-18 22:16:13', '2026-04-18 22:16:13'),
(681, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/cable-management-systems', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:16:15', '2026-04-18 22:16:15', '2026-04-18 22:16:15'),
(682, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/cable-management-systems', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:16:38', '2026-04-18 22:16:38', '2026-04-18 22:16:38'),
(683, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/blockwork-plastering-accessories', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:16:40', '2026-04-18 22:16:40', '2026-04-18 22:16:40'),
(684, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/category/blockwork-plastering-accessories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:53:23', '2026-04-18 22:53:23', '2026-04-18 22:53:23'),
(685, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/vision', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:53:32', '2026-04-18 22:53:32', '2026-04-18 22:53:32'),
(686, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/about', 'http://awan.test/vision', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:53:41', '2026-04-18 22:53:41', '2026-04-18 22:53:41'),
(687, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/admin', 'http://awan.test/admin/products', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 22:54:08', '2026-04-18 22:54:08', '2026-04-18 22:54:08'),
(688, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/about', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 23:00:44', '2026-04-18 23:00:44', '2026-04-18 23:00:44'),
(689, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 23:00:47', '2026-04-18 23:00:47', '2026-04-18 23:00:47'),
(690, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions?page=2', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 23:00:52', '2026-04-18 23:00:52', '2026-04-18 23:00:52'),
(691, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/architectural-building-solutions?page=2', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 23:02:50', '2026-04-18 23:02:50', '2026-04-18 23:02:50'),
(692, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 23:02:54', '2026-04-18 23:02:54', '2026-04-18 23:02:54'),
(693, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 23:03:49', '2026-04-18 23:03:49', '2026-04-18 23:03:49'),
(694, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 23:03:52', '2026-04-18 23:03:52', '2026-04-18 23:03:52'),
(695, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 23:04:04', '2026-04-18 23:04:04', '2026-04-18 23:04:04'),
(696, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/cable-management-systems', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 23:04:07', '2026-04-18 23:04:07', '2026-04-18 23:04:07'),
(697, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/cable-management-systems', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 23:04:12', '2026-04-18 23:04:12', '2026-04-18 23:04:12'),
(698, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/blockwork-plastering-accessories', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 23:04:14', '2026-04-18 23:04:14', '2026-04-18 23:04:14'),
(699, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/featured-products', 'http://awan.test/category/blockwork-plastering-accessories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 23:04:18', '2026-04-18 23:04:18', '2026-04-18 23:04:18'),
(700, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/featured-products', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 23:04:41', '2026-04-18 23:04:41', '2026-04-18 23:04:41'),
(701, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/admin', 'http://awan.test/admin/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 23:07:12', '2026-04-18 23:07:12', '2026-04-18 23:07:12'),
(702, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 23:08:35', '2026-04-18 23:08:35', '2026-04-18 23:08:35'),
(703, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-steel-bollards', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 23:08:41', '2026-04-18 23:08:41', '2026-04-18 23:08:41'),
(704, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/product/architectural-building-solutions-steel-bollards', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 23:09:10', '2026-04-18 23:09:10', '2026-04-18 23:09:10'),
(705, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/cable-management-systems', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 23:09:12', '2026-04-18 23:09:12', '2026-04-18 23:09:12'),
(706, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/cable-management-systems', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 23:09:17', '2026-04-18 23:09:17', '2026-04-18 23:09:17'),
(707, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/gypsum-partitions-suspended-ceilings', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 23:09:19', '2026-04-18 23:09:19', '2026-04-18 23:09:19'),
(708, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/gypsum-partitions-suspended-ceilings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 23:09:27', '2026-04-18 23:09:27', '2026-04-18 23:09:27'),
(709, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/consumable-items-hardware', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 23:09:30', '2026-04-18 23:09:30', '2026-04-18 23:09:30'),
(710, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/category/consumable-items-hardware', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 23:11:01', '2026-04-18 23:11:01', '2026-04-18 23:11:01'),
(711, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 23:11:09', '2026-04-18 23:11:09', '2026-04-18 23:11:09'),
(712, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 23:11:15', '2026-04-18 23:11:15', '2026-04-18 23:11:15'),
(713, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 23:11:27', '2026-04-18 23:11:27', '2026-04-18 23:11:27'),
(714, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 23:11:47', '2026-04-18 23:11:47', '2026-04-18 23:11:47'),
(715, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 23:11:51', '2026-04-18 23:11:51', '2026-04-18 23:11:51'),
(716, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 23:11:58', '2026-04-18 23:11:58', '2026-04-18 23:11:58'),
(717, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/admin', 'http://awan.test/admin/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-18 23:12:25', '2026-04-18 23:12:25', '2026-04-18 23:12:25'),
(718, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-19 15:31:02', '2026-04-19 15:31:02', '2026-04-19 15:31:02'),
(719, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-19 15:36:07', '2026-04-19 15:36:07', '2026-04-19 15:36:07'),
(720, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-19 15:45:07', '2026-04-19 15:45:07', '2026-04-19 15:45:07'),
(721, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/cable-management-systems', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-19 15:45:17', '2026-04-19 15:45:17', '2026-04-19 15:45:17'),
(722, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/category/cable-management-systems', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-19 15:46:09', '2026-04-19 15:46:09', '2026-04-19 15:46:09'),
(723, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-19 15:46:25', '2026-04-19 15:46:25', '2026-04-19 15:46:25'),
(724, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-19 15:50:23', '2026-04-19 15:50:23', '2026-04-19 15:50:23'),
(725, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-19 15:51:43', '2026-04-19 15:51:43', '2026-04-19 15:51:43'),
(726, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-19 15:55:36', '2026-04-19 15:55:36', '2026-04-19 15:55:36'),
(727, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/metal-work-engineering-solution', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-19 15:55:40', '2026-04-19 15:55:40', '2026-04-19 15:55:40'),
(728, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/category/metal-work-engineering-solution', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-19 16:00:14', '2026-04-19 16:00:14', '2026-04-19 16:00:14'),
(729, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-19 16:00:21', '2026-04-19 16:00:21', '2026-04-19 16:00:21'),
(730, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions?page=2', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-19 16:00:26', '2026-04-19 16:00:26', '2026-04-19 16:00:26'),
(731, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions?page=1', 'http://awan.test/category/architectural-building-solutions?page=2', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-19 16:00:35', '2026-04-19 16:00:35', '2026-04-19 16:00:35'),
(732, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/category/architectural-building-solutions?page=1', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-19 16:19:38', '2026-04-19 16:19:38', '2026-04-19 16:19:38');
INSERT INTO `visitors` (`id`, `ip_address`, `user_agent`, `page_url`, `referrer`, `country`, `city`, `device_type`, `browser`, `os`, `is_bot`, `visited_at`, `created_at`, `updated_at`) VALUES
(733, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-19 16:19:48', '2026-04-19 16:19:48', '2026-04-19 16:19:48'),
(734, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions?page=2', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-19 16:19:55', '2026-04-19 16:19:55', '2026-04-19 16:19:55'),
(735, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/featured-products', 'http://awan.test/category/architectural-building-solutions?page=2', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-19 16:20:06', '2026-04-19 16:20:06', '2026-04-19 16:20:06'),
(736, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/featured-products', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-19 16:26:59', '2026-04-19 16:26:59', '2026-04-19 16:26:59'),
(737, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/cable-management-systems', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-19 16:42:08', '2026-04-19 16:42:08', '2026-04-19 16:42:08'),
(738, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/cable-management-systems-cable-ladder-basket-tray', 'http://awan.test/category/cable-management-systems', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-19 16:42:13', '2026-04-19 16:42:13', '2026-04-19 16:42:13'),
(739, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/admin', 'http://awan.test/admin/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-19 16:50:14', '2026-04-19 16:50:14', '2026-04-19 16:50:14'),
(740, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/product/cable-management-systems-cable-ladder-basket-tray', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-19 16:50:27', '2026-04-19 16:50:27', '2026-04-19 16:50:27'),
(741, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-19 16:50:59', '2026-04-19 16:50:59', '2026-04-19 16:50:59'),
(742, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-19 16:51:02', '2026-04-19 16:51:02', '2026-04-19 16:51:02'),
(743, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/featured-products', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-19 17:34:47', '2026-04-19 17:34:47', '2026-04-19 17:34:47'),
(744, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/featured-products?page=2', 'http://awan.test/featured-products', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-19 17:34:52', '2026-04-19 17:34:52', '2026-04-19 17:34:52'),
(745, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/featured-products?page=3', 'http://awan.test/featured-products?page=2', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-19 17:34:58', '2026-04-19 17:34:58', '2026-04-19 17:34:58'),
(746, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/featured-products?page=3', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-19 17:35:03', '2026-04-19 17:35:03', '2026-04-19 17:35:03'),
(747, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/admin', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-24 13:08:10', '2026-04-24 13:08:10', '2026-04-24 13:08:10'),
(748, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/admin', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-24 13:08:14', '2026-04-24 13:08:14', '2026-04-24 13:08:14'),
(749, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-24 13:08:20', '2026-04-24 13:08:20', '2026-04-24 13:08:20'),
(750, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-24 13:08:28', '2026-04-24 13:08:28', '2026-04-24 13:08:28'),
(751, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions?page=2', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-24 13:08:34', '2026-04-24 13:08:34', '2026-04-24 13:08:34'),
(752, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions?page=1', 'http://awan.test/category/architectural-building-solutions?page=2', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-24 13:08:41', '2026-04-24 13:08:41', '2026-04-24 13:08:41'),
(753, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/architectural-building-solutions?page=1', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-24 13:08:55', '2026-04-24 13:08:55', '2026-04-24 13:08:55'),
(754, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/metal-work-engineering-solution', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-24 13:08:58', '2026-04-24 13:08:58', '2026-04-24 13:08:58'),
(755, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/architectural-building-solutions-lockers', 'http://awan.test/category/metal-work-engineering-solution', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-24 13:09:03', '2026-04-24 13:09:03', '2026-04-24 13:09:03'),
(756, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/admin', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:22:11', '2026-04-25 13:22:11', '2026-04-25 13:22:11'),
(757, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:22:21', '2026-04-25 13:22:21', '2026-04-25 13:22:21'),
(758, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:22:52', '2026-04-25 13:22:52', '2026-04-25 13:22:52'),
(759, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:23:04', '2026-04-25 13:23:04', '2026-04-25 13:23:04'),
(760, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/cable-management-systems', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:23:08', '2026-04-25 13:23:08', '2026-04-25 13:23:08'),
(761, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/cable-management-systems', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:23:13', '2026-04-25 13:23:13', '2026-04-25 13:23:13'),
(762, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/blockwork-plastering-accessories', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:23:16', '2026-04-25 13:23:16', '2026-04-25 13:23:16'),
(763, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/blockwork-plastering-accessories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:23:22', '2026-04-25 13:23:22', '2026-04-25 13:23:22'),
(764, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/concrete-formwork-accessories', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:23:26', '2026-04-25 13:23:26', '2026-04-25 13:23:26'),
(765, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/concrete-formwork-accessories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:23:44', '2026-04-25 13:23:44', '2026-04-25 13:23:44'),
(766, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/pipe-clamps-hangers-fixings', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:23:47', '2026-04-25 13:23:47', '2026-04-25 13:23:47'),
(767, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/pipe-clamps-hangers-fixings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:23:55', '2026-04-25 13:23:55', '2026-04-25 13:23:55'),
(768, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/gypsum-partitions-suspended-ceilings', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:23:58', '2026-04-25 13:23:58', '2026-04-25 13:23:58'),
(769, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/gypsum-partitions-suspended-ceilings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:24:43', '2026-04-25 13:24:43', '2026-04-25 13:24:43'),
(770, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/cladding-facade-accessories', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:24:46', '2026-04-25 13:24:46', '2026-04-25 13:24:46'),
(771, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/product/cladding-facade-accessories-bracket', 'http://awan.test/category/cladding-facade-accessories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:24:56', '2026-04-25 13:24:56', '2026-04-25 13:24:56'),
(772, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/product/cladding-facade-accessories-bracket', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:25:13', '2026-04-25 13:25:13', '2026-04-25 13:25:13'),
(773, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/consumable-items-hardware', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:25:17', '2026-04-25 13:25:17', '2026-04-25 13:25:17'),
(774, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/consumable-items-hardware', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:34:24', '2026-04-25 13:34:24', '2026-04-25 13:34:24'),
(775, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/plumbing-and-sanitary-materials', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:34:27', '2026-04-25 13:34:27', '2026-04-25 13:34:27'),
(776, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/plumbing-and-sanitary-materials', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:34:34', '2026-04-25 13:34:34', '2026-04-25 13:34:34'),
(777, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:34:37', '2026-04-25 13:34:37', '2026-04-25 13:34:37'),
(778, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:34:52', '2026-04-25 13:34:52', '2026-04-25 13:34:52'),
(779, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/metal-work-engineering-solution', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:34:58', '2026-04-25 13:34:58', '2026-04-25 13:34:58'),
(780, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/category/metal-work-engineering-solution', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:37:41', '2026-04-25 13:37:41', '2026-04-25 13:37:41'),
(781, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:37:48', '2026-04-25 13:37:48', '2026-04-25 13:37:48'),
(782, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:37:51', '2026-04-25 13:37:51', '2026-04-25 13:37:51'),
(783, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:38:27', '2026-04-25 13:38:27', '2026-04-25 13:38:27'),
(784, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:38:31', '2026-04-25 13:38:31', '2026-04-25 13:38:31'),
(785, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:38:38', '2026-04-25 13:38:38', '2026-04-25 13:38:38'),
(786, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/cable-management-systems', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:38:43', '2026-04-25 13:38:43', '2026-04-25 13:38:43'),
(787, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/cable-management-systems', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:38:46', '2026-04-25 13:38:46', '2026-04-25 13:38:46'),
(788, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/blockwork-plastering-accessories', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:38:48', '2026-04-25 13:38:48', '2026-04-25 13:38:48'),
(789, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/blockwork-plastering-accessories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:38:53', '2026-04-25 13:38:53', '2026-04-25 13:38:53'),
(790, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/concrete-formwork-accessories', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:38:55', '2026-04-25 13:38:55', '2026-04-25 13:38:55'),
(791, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/category/concrete-formwork-accessories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:39:15', '2026-04-25 13:39:15', '2026-04-25 13:39:15'),
(792, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:39:21', '2026-04-25 13:39:21', '2026-04-25 13:39:21'),
(793, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:39:24', '2026-04-25 13:39:24', '2026-04-25 13:39:24'),
(794, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:39:27', '2026-04-25 13:39:27', '2026-04-25 13:39:27'),
(795, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/metal-work-engineering-solution', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:39:29', '2026-04-25 13:39:29', '2026-04-25 13:39:29'),
(796, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/metal-work-engineering-solution', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:40:05', '2026-04-25 13:40:05', '2026-04-25 13:40:05'),
(797, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:40:07', '2026-04-25 13:40:07', '2026-04-25 13:40:07'),
(798, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:40:37', '2026-04-25 13:40:37', '2026-04-25 13:40:37'),
(799, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:47:39', '2026-04-25 13:47:39', '2026-04-25 13:47:39'),
(800, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/plumbing-and-sanitary-materials', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:47:43', '2026-04-25 13:47:43', '2026-04-25 13:47:43'),
(801, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/plumbing-and-sanitary-materials', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:50:55', '2026-04-25 13:50:55', '2026-04-25 13:50:55'),
(802, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:50:57', '2026-04-25 13:50:57', '2026-04-25 13:50:57'),
(803, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions?page=2', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:51:04', '2026-04-25 13:51:04', '2026-04-25 13:51:04'),
(804, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions?page=1', 'http://awan.test/category/architectural-building-solutions?page=2', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:51:09', '2026-04-25 13:51:09', '2026-04-25 13:51:09'),
(805, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions?page=1', 'http://awan.test/category/architectural-building-solutions?page=2', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:51:31', '2026-04-25 13:51:31', '2026-04-25 13:51:31'),
(806, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions?page=2', 'http://awan.test/category/architectural-building-solutions?page=1', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:51:37', '2026-04-25 13:51:37', '2026-04-25 13:51:37'),
(807, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/architectural-building-solutions?page=2', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:51:56', '2026-04-25 13:51:56', '2026-04-25 13:51:56'),
(808, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:51:59', '2026-04-25 13:51:59', '2026-04-25 13:51:59'),
(809, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:52:21', '2026-04-25 13:52:21', '2026-04-25 13:52:21'),
(810, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:52:23', '2026-04-25 13:52:23', '2026-04-25 13:52:23'),
(811, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions?page=2', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:52:27', '2026-04-25 13:52:27', '2026-04-25 13:52:27'),
(812, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions?page=2', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:52:27', '2026-04-25 13:52:27', '2026-04-25 13:52:27'),
(813, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/architectural-building-solutions?page=2', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:52:39', '2026-04-25 13:52:39', '2026-04-25 13:52:39'),
(814, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:52:41', '2026-04-25 13:52:41', '2026-04-25 13:52:41'),
(815, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/admin', 'http://awan.test/admin', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:54:57', '2026-04-25 13:54:57', '2026-04-25 13:54:57'),
(816, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/product/architectural-building-solutions-lockers', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:56:15', '2026-04-25 13:56:15', '2026-04-25 13:56:15'),
(817, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:56:23', '2026-04-25 13:56:23', '2026-04-25 13:56:23'),
(818, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/plumbing-and-sanitary-materials', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:56:34', '2026-04-25 13:56:34', '2026-04-25 13:56:34'),
(819, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/admin', 'http://awan.test/admin/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 13:57:48', '2026-04-25 13:57:48', '2026-04-25 13:57:48'),
(820, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/category/plumbing-and-sanitary-materials', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 14:00:00', '2026-04-25 14:00:00', '2026-04-25 14:00:00'),
(821, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 14:00:06', '2026-04-25 14:00:06', '2026-04-25 14:00:06'),
(822, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/consumable-items-hardware', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 14:00:12', '2026-04-25 14:00:12', '2026-04-25 14:00:12'),
(823, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/consumable-items-hardware', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 14:00:16', '2026-04-25 14:00:16', '2026-04-25 14:00:16'),
(824, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/traffic-safety-control', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 14:00:19', '2026-04-25 14:00:19', '2026-04-25 14:00:19'),
(825, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/traffic-safety-control', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 14:00:23', '2026-04-25 14:00:23', '2026-04-25 14:00:23'),
(826, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/cladding-facade-accessories', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 14:00:26', '2026-04-25 14:00:26', '2026-04-25 14:00:26'),
(827, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/category/cladding-facade-accessories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 14:00:32', '2026-04-25 14:00:32', '2026-04-25 14:00:32'),
(828, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 14:00:40', '2026-04-25 14:00:40', '2026-04-25 14:00:40'),
(829, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/gypsum-partitions-suspended-ceilings', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 14:00:44', '2026-04-25 14:00:44', '2026-04-25 14:00:44'),
(830, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/gypsum-partitions-suspended-ceilings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 14:00:49', '2026-04-25 14:00:49', '2026-04-25 14:00:49'),
(831, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/pipe-clamps-hangers-fixings', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 14:00:51', '2026-04-25 14:00:51', '2026-04-25 14:00:51'),
(832, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/pipe-clamps-hangers-fixings', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 14:01:13', '2026-04-25 14:01:13', '2026-04-25 14:01:13'),
(833, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/waterproofing-thermal-insulation', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 14:01:17', '2026-04-25 14:01:17', '2026-04-25 14:01:17'),
(834, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/waterproofing-thermal-insulation', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 14:01:29', '2026-04-25 14:01:29', '2026-04-25 14:01:29'),
(835, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/concrete-formwork-accessories', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 14:01:36', '2026-04-25 14:01:36', '2026-04-25 14:01:36'),
(836, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/concrete-formwork-accessories', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 14:09:18', '2026-04-25 14:09:18', '2026-04-25 14:09:18'),
(837, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/concrete-formwork-accessories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 14:09:22', '2026-04-25 14:09:22', '2026-04-25 14:09:22'),
(838, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 14:39:23', '2026-04-25 14:39:23', '2026-04-25 14:39:23'),
(839, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 14:39:44', '2026-04-25 14:39:44', '2026-04-25 14:39:44'),
(840, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/traffic-safety-control', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 14:45:56', '2026-04-25 14:45:56', '2026-04-25 14:45:56'),
(841, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/category/traffic-safety-control', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 14:46:01', '2026-04-25 14:46:01', '2026-04-25 14:46:01'),
(842, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/architectural-building-solutions', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 14:46:06', '2026-04-25 14:46:06', '2026-04-25 14:46:06'),
(843, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/about', 'http://awan.test/category/architectural-building-solutions', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 14:46:09', '2026-04-25 14:46:09', '2026-04-25 14:46:09'),
(844, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/contact', 'http://awan.test/about', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 14:46:14', '2026-04-25 14:46:14', '2026-04-25 14:46:14'),
(845, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/contact', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 14:46:37', '2026-04-25 14:46:37', '2026-04-25 14:46:37'),
(846, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test', 'http://awan.test/contact', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 15:59:17', '2026-04-25 15:59:17', '2026-04-25 15:59:17'),
(847, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/categories', 'http://awan.test/', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 16:08:15', '2026-04-25 16:08:15', '2026-04-25 16:08:15'),
(848, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/consumable-items-hardware', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-25 16:08:20', '2026-04-25 16:08:20', '2026-04-25 16:08:20'),
(849, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'http://awan.test/category/consumable-items-hardware', 'http://awan.test/categories', 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-04-26 10:08:15', '2026-04-26 10:08:15', '2026-04-26 10:08:15'),
(850, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'http://awan.test/about', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-06-26 02:12:31', '2026-06-26 02:12:31', '2026-06-26 02:12:31'),
(851, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'http://awan.test/about', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-06-26 02:28:05', '2026-06-26 02:28:05', '2026-06-26 02:28:05'),
(852, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'http://awan.test/about', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-06-26 02:29:11', '2026-06-26 02:29:11', '2026-06-26 02:29:11'),
(853, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-06-26 02:30:26', '2026-06-26 02:30:26', '2026-06-26 02:30:26'),
(854, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'http://awan.test/about', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-06-26 02:32:08', '2026-06-26 02:32:08', '2026-06-26 02:32:08'),
(855, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'http://awan.test/categories', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-06-26 02:33:32', '2026-06-26 02:33:32', '2026-06-26 02:33:32'),
(856, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-06-26 02:45:16', '2026-06-26 02:45:16', '2026-06-26 02:45:16'),
(857, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-06-26 02:45:20', '2026-06-26 02:45:20', '2026-06-26 02:45:20'),
(858, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-06-26 02:58:13', '2026-06-26 02:58:13', '2026-06-26 02:58:13'),
(859, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'http://awan.test/admin', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-06-26 02:58:22', '2026-06-26 02:58:22', '2026-06-26 02:58:22'),
(860, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-06-26 02:58:58', '2026-06-26 02:58:58', '2026-06-26 02:58:58'),
(861, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-06-26 03:02:56', '2026-06-26 03:02:56', '2026-06-26 03:02:56'),
(862, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-06-26 03:03:19', '2026-06-26 03:03:19', '2026-06-26 03:03:19'),
(863, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-06-26 03:04:23', '2026-06-26 03:04:23', '2026-06-26 03:04:23'),
(864, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-06-26 03:05:01', '2026-06-26 03:05:01', '2026-06-26 03:05:01'),
(865, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-06-26 03:06:24', '2026-06-26 03:06:24', '2026-06-26 03:06:24'),
(866, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-06-26 03:06:31', '2026-06-26 03:06:31', '2026-06-26 03:06:31'),
(867, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'http://awan.test', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-06-26 03:07:51', '2026-06-26 03:07:51', '2026-06-26 03:07:51'),
(868, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'http://awan.test/contact', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-06-26 03:18:19', '2026-06-26 03:18:19', '2026-06-26 03:18:19'),
(869, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'http://awan.test/contact', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-06-26 03:22:48', '2026-06-26 03:22:48', '2026-06-26 03:22:48'),
(870, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'http://awan.test/contact', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-06-26 03:23:08', '2026-06-26 03:23:08', '2026-06-26 03:23:08'),
(871, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'http://awan.test/contact', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-06-26 03:24:42', '2026-06-26 03:24:42', '2026-06-26 03:24:42'),
(872, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'http://awan.test/contact', NULL, 'SY', NULL, 'desktop', 'Chrome', 'Windows', 0, '2026-06-26 03:25:07', '2026-06-26 03:25:07', '2026-06-26 03:25:07');

-- --------------------------------------------------------

--
-- بنية الجدول `wallet_transactions`
--

CREATE TABLE `wallet_transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `type` enum('credit','debit') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `products` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`products`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `warehouses`
--

CREATE TABLE `warehouses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `location_type` enum('warehouse','branch','distribution_center','3pl') NOT NULL DEFAULT 'warehouse',
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  `operating_hours` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`operating_hours`)),
  `manager_name` varchar(255) DEFAULT NULL,
  `manager_phone` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `manager_id` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `warehouse_bins`
--

CREATE TABLE `warehouse_bins` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `warehouse_id` bigint(20) UNSIGNED NOT NULL,
  `bin_code` varchar(255) NOT NULL,
  `zone` varchar(255) DEFAULT NULL,
  `rack` varchar(255) DEFAULT NULL,
  `shelf` varchar(255) DEFAULT NULL,
  `max_weight` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `warehouse_inventory`
--

CREATE TABLE `warehouse_inventory` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `warehouse_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `product_variant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `available_quantity` int(11) NOT NULL DEFAULT 0,
  `damaged_quantity` int(11) NOT NULL DEFAULT 0,
  `quarantined_quantity` int(11) NOT NULL DEFAULT 0,
  `reserved_quantity` int(11) NOT NULL DEFAULT 0,
  `reorder_point` int(11) NOT NULL DEFAULT 10,
  `safety_stock` int(11) NOT NULL DEFAULT 5,
  `lead_time_days` int(11) NOT NULL DEFAULT 7,
  `average_daily_sales` decimal(10,2) NOT NULL DEFAULT 0.00,
  `last_reorder_at` timestamp NULL DEFAULT NULL,
  `auto_reorder_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `bin_id` bigint(20) UNSIGNED DEFAULT NULL,
  `batch_number` varchar(255) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `serial_numbers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`serial_numbers`)),
  `cost_basis` enum('FIFO','FEFO','LIFO') NOT NULL DEFAULT 'FIFO',
  `last_counted_at` timestamp NULL DEFAULT NULL,
  `count_variance` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `wishlist_items`
--

CREATE TABLE `wishlist_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `workflows`
--

CREATE TABLE `workflows` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `name_ar` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `trigger_type` varchar(255) NOT NULL DEFAULT 'manual',
  `trigger_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`trigger_config`)),
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `workflow_executions`
--

CREATE TABLE `workflow_executions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `workflow_id` bigint(20) UNSIGNED NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `triggered_by` bigint(20) UNSIGNED DEFAULT NULL,
  `entity_type` varchar(255) DEFAULT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `input_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`input_data`)),
  `output_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`output_data`)),
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `workflow_steps`
--

CREATE TABLE `workflow_steps` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `workflow_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `name_ar` varchar(255) DEFAULT NULL,
  `action_type` varchar(255) NOT NULL DEFAULT 'task',
  `action_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`action_config`)),
  `order` int(11) NOT NULL DEFAULT 0,
  `condition_type` varchar(255) DEFAULT NULL,
  `condition_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`condition_config`)),
  `is_parallel` tinyint(1) NOT NULL DEFAULT 0,
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  `assigned_to` bigint(20) UNSIGNED DEFAULT NULL,
  `estimated_duration` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `addresses`
--
ALTER TABLE `addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `addresses_user_id_foreign` (`user_id`);

--
-- Indexes for table `analytics_data_points`
--
ALTER TABLE `analytics_data_points`
  ADD PRIMARY KEY (`id`),
  ADD KEY `analytics_data_points_metric_id_recorded_date_index` (`metric_id`,`recorded_date`),
  ADD KEY `analytics_data_points_warehouse_id_recorded_date_index` (`warehouse_id`,`recorded_date`),
  ADD KEY `analytics_data_points_channel_id_recorded_date_index` (`channel_id`,`recorded_date`),
  ADD KEY `analytics_data_points_recorded_date_index` (`recorded_date`);

--
-- Indexes for table `analytics_metrics`
--
ALTER TABLE `analytics_metrics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `analytics_metrics_metric_key_unique` (`metric_key`),
  ADD KEY `analytics_metrics_category_is_active_index` (`category`,`is_active`);

--
-- Indexes for table `attendances`
--
ALTER TABLE `attendances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `attendances_employee_id_date_unique` (`employee_id`,`date`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `audit_logs_user_id_foreign` (`user_id`),
  ADD KEY `audit_logs_entity_type_entity_id_index` (`entity_type`,`entity_id`),
  ADD KEY `audit_logs_created_at_index` (`created_at`),
  ADD KEY `audit_logs_action_index` (`action`),
  ADD KEY `audit_logs_entity_id_index` (`entity_id`),
  ADD KEY `audit_logs_module_index` (`module`);

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_expiration_index` (`expiration`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_locks_expiration_index` (`expiration`);

--
-- Indexes for table `carts`
--
ALTER TABLE `carts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `carts_session_id_index` (`session_id`),
  ADD KEY `carts_user_id_index` (`user_id`);

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cart_items_cart_id_index` (`cart_id`),
  ADD KEY `cart_items_product_id_index` (`product_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `categories_is_active_sort_order_index` (`is_active`,`sort_order`),
  ADD KEY `categories_slug_index` (`slug`),
  ADD KEY `categories_parent_id_foreign` (`parent_id`);

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `contacts`
--
ALTER TABLE `contacts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contacts_contactable_type_contactable_id_index` (`contactable_type`,`contactable_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `customers_auth_token_unique` (`auth_token`);

--
-- Indexes for table `cycle_counts`
--
ALTER TABLE `cycle_counts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cycle_counts_count_number_unique` (`count_number`),
  ADD KEY `cycle_counts_reviewer_id_foreign` (`reviewer_id`),
  ADD KEY `cycle_counts_adjustment_by_foreign` (`adjustment_by`),
  ADD KEY `cycle_counts_warehouse_id_status_index` (`warehouse_id`,`status`),
  ADD KEY `cycle_counts_bin_id_index` (`bin_id`),
  ADD KEY `cycle_counts_counter_id_index` (`counter_id`);

--
-- Indexes for table `cycle_count_items`
--
ALTER TABLE `cycle_count_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cycle_count_items_product_variant_id_foreign` (`product_variant_id`),
  ADD KEY `cycle_count_items_cycle_count_id_index` (`cycle_count_id`),
  ADD KEY `cycle_count_items_product_id_index` (`product_id`),
  ADD KEY `cycle_count_items_bin_id_index` (`bin_id`);

--
-- Indexes for table `dashboards`
--
ALTER TABLE `dashboards`
  ADD PRIMARY KEY (`id`),
  ADD KEY `dashboards_type_is_public_index` (`type`,`is_public`),
  ADD KEY `dashboards_created_by_index` (`created_by`);

--
-- Indexes for table `dashboard_widgets`
--
ALTER TABLE `dashboard_widgets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `dashboard_widgets_metric_id_foreign` (`metric_id`),
  ADD KEY `dashboard_widgets_report_id_foreign` (`report_id`),
  ADD KEY `dashboard_widgets_dashboard_id_is_active_index` (`dashboard_id`,`is_active`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employees_national_id_index` (`national_id`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `inquiries`
--
ALTER TABLE `inquiries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inquiries_product_id_foreign` (`product_id`),
  ADD KEY `inquiries_user_id_foreign` (`user_id`),
  ADD KEY `inquiries_status_index` (`status`),
  ADD KEY `inquiries_priority_index` (`priority`),
  ADD KEY `inquiries_assigned_to_index` (`assigned_to`),
  ADD KEY `inquiries_created_at_index` (`created_at`);

--
-- Indexes for table `inquiry_replies`
--
ALTER TABLE `inquiry_replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inquiry_replies_inquiry_id_index` (`inquiry_id`),
  ADD KEY `inquiry_replies_admin_id_index` (`admin_id`);

--
-- Indexes for table `integration_settings`
--
ALTER TABLE `integration_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventory_transfers`
--
ALTER TABLE `inventory_transfers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `inventory_transfers_transfer_number_unique` (`transfer_number`),
  ADD KEY `inventory_transfers_from_warehouse_id_foreign` (`from_warehouse_id`),
  ADD KEY `inventory_transfers_to_warehouse_id_foreign` (`to_warehouse_id`),
  ADD KEY `inventory_transfers_created_by_foreign` (`created_by`),
  ADD KEY `inventory_transfers_shipped_by_foreign` (`shipped_by`),
  ADD KEY `inventory_transfers_received_by_foreign` (`received_by`);

--
-- Indexes for table `inventory_transfer_items`
--
ALTER TABLE `inventory_transfer_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inventory_transfer_items_transfer_id_foreign` (`transfer_id`),
  ADD KEY `inventory_transfer_items_product_id_foreign` (`product_id`),
  ADD KEY `inventory_transfer_items_product_variant_id_foreign` (`product_variant_id`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoices_invoice_number_unique` (`invoice_number`),
  ADD KEY `invoices_created_by_foreign` (`created_by`),
  ADD KEY `invoices_customer_id_foreign` (`customer_id`),
  ADD KEY `invoices_sales_order_id_foreign` (`sales_order_id`);

--
-- Indexes for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_items_invoice_id_foreign` (`invoice_id`),
  ADD KEY `invoice_items_product_id_foreign` (`product_id`),
  ADD KEY `invoice_items_product_unit_id_foreign` (`product_unit_id`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indexes for table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `journal_entries`
--
ALTER TABLE `journal_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `journal_entries_ledger_account_id_foreign` (`ledger_account_id`),
  ADD KEY `journal_entries_created_by_foreign` (`created_by`);

--
-- Indexes for table `landed_costs`
--
ALTER TABLE `landed_costs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `landed_costs_purchase_receipt_id_foreign` (`purchase_receipt_id`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `leave_requests_employee_id_foreign` (`employee_id`),
  ADD KEY `leave_requests_approved_by_foreign` (`approved_by`);

--
-- Indexes for table `ledger_accounts`
--
ALTER TABLE `ledger_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ledger_accounts_code_unique` (`code`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notifications_user_id_foreign` (`user_id`);

--
-- Indexes for table `notification_preferences`
--
ALTER TABLE `notification_preferences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `notification_preferences_user_id_notification_type_unique` (`user_id`,`notification_type`);

--
-- Indexes for table `notification_templates`
--
ALTER TABLE `notification_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `notification_templates_template_key_unique` (`template_key`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `orders_order_number_unique` (`order_number`),
  ADD KEY `orders_user_id_foreign` (`user_id`),
  ADD KEY `orders_shipping_address_id_foreign` (`shipping_address_id`),
  ADD KEY `orders_payment_method_id_foreign` (`payment_method_id`);

--
-- Indexes for table `order_channels`
--
ALTER TABLE `order_channels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_channels_code_unique` (`code`),
  ADD KEY `order_channels_type_is_active_index` (`type`,`is_active`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_items_order_id_foreign` (`order_id`),
  ADD KEY `order_items_product_id_foreign` (`product_id`);

--
-- Indexes for table `packing_lists`
--
ALTER TABLE `packing_lists`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `packing_lists_list_number_unique` (`list_number`),
  ADD KEY `packing_lists_picking_list_id_foreign` (`picking_list_id`),
  ADD KEY `packing_lists_sales_order_id_foreign` (`sales_order_id`),
  ADD KEY `packing_lists_warehouse_id_status_index` (`warehouse_id`,`status`),
  ADD KEY `packing_lists_packer_id_index` (`packer_id`);

--
-- Indexes for table `packing_list_items`
--
ALTER TABLE `packing_list_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `packing_list_items_picking_list_item_id_foreign` (`picking_list_item_id`),
  ADD KEY `packing_list_items_product_id_foreign` (`product_id`),
  ADD KEY `packing_list_items_product_variant_id_foreign` (`product_variant_id`),
  ADD KEY `packing_list_items_packing_list_id_index` (`packing_list_id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payments_payment_number_unique` (`payment_number`),
  ADD KEY `payments_invoice_id_foreign` (`invoice_id`),
  ADD KEY `payments_customer_id_foreign` (`customer_id`),
  ADD KEY `payments_created_by_foreign` (`created_by`),
  ADD KEY `payments_sales_order_id_foreign` (`sales_order_id`),
  ADD KEY `payments_purchase_order_id_foreign` (`purchase_order_id`);

--
-- Indexes for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`id`),
  ADD KEY `payment_methods_user_id_foreign` (`user_id`);

--
-- Indexes for table `payrolls`
--
ALTER TABLE `payrolls`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payrolls_payroll_number_unique` (`payroll_number`),
  ADD KEY `payrolls_employee_id_foreign` (`employee_id`),
  ADD KEY `payrolls_created_by_foreign` (`created_by`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permissions_name_unique` (`name`);

--
-- Indexes for table `permission_role`
--
ALTER TABLE `permission_role`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permission_role_permission_id_role_id_unique` (`permission_id`,`role_id`),
  ADD KEY `permission_role_role_id_foreign` (`role_id`);

--
-- Indexes for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  ADD KEY `personal_access_tokens_expires_at_index` (`expires_at`);

--
-- Indexes for table `picking_lists`
--
ALTER TABLE `picking_lists`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `picking_lists_list_number_unique` (`list_number`),
  ADD KEY `picking_lists_sales_order_id_foreign` (`sales_order_id`),
  ADD KEY `picking_lists_warehouse_id_status_index` (`warehouse_id`,`status`),
  ADD KEY `picking_lists_picker_id_index` (`picker_id`),
  ADD KEY `picking_lists_status_priority_index` (`status`,`priority`);

--
-- Indexes for table `picking_list_items`
--
ALTER TABLE `picking_list_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `picking_list_items_sales_order_item_id_foreign` (`sales_order_item_id`),
  ADD KEY `picking_list_items_product_id_foreign` (`product_id`),
  ADD KEY `picking_list_items_product_variant_id_foreign` (`product_variant_id`),
  ADD KEY `picking_list_items_picking_list_id_status_index` (`picking_list_id`,`status`),
  ADD KEY `picking_list_items_bin_id_index` (`bin_id`);

--
-- Indexes for table `production_orders`
--
ALTER TABLE `production_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `production_orders_order_number_unique` (`order_number`),
  ADD KEY `production_orders_product_id_foreign` (`product_id`),
  ADD KEY `production_orders_created_by_foreign` (`created_by`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `products_sku_unique` (`sku`),
  ADD KEY `products_category_id_is_active_is_featured_in_stock_index` (`category_id`,`is_active`,`is_featured`,`in_stock`),
  ADD KEY `products_slug_index` (`slug`);

--
-- Indexes for table `product_batches`
--
ALTER TABLE `product_batches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_batches_batch_number_unique` (`batch_number`),
  ADD KEY `product_batches_product_variant_id_foreign` (`product_variant_id`),
  ADD KEY `product_batches_warehouse_id_foreign` (`warehouse_id`),
  ADD KEY `product_batches_product_id_warehouse_id_index` (`product_id`,`warehouse_id`),
  ADD KEY `product_batches_expiry_date_index` (`expiry_date`);

--
-- Indexes for table `product_serial_numbers`
--
ALTER TABLE `product_serial_numbers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_serial_numbers_serial_number_unique` (`serial_number`),
  ADD KEY `product_serial_numbers_product_variant_id_foreign` (`product_variant_id`),
  ADD KEY `product_serial_numbers_warehouse_id_foreign` (`warehouse_id`),
  ADD KEY `product_serial_numbers_batch_id_foreign` (`batch_id`),
  ADD KEY `product_serial_numbers_sale_order_id_foreign` (`sale_order_id`),
  ADD KEY `product_serial_numbers_sale_order_item_id_foreign` (`sale_order_item_id`),
  ADD KEY `product_serial_numbers_product_id_warehouse_id_index` (`product_id`,`warehouse_id`),
  ADD KEY `product_serial_numbers_serial_number_index` (`serial_number`),
  ADD KEY `product_serial_numbers_status_index` (`status`);

--
-- Indexes for table `product_units`
--
ALTER TABLE `product_units`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_units_product_id_foreign` (`product_id`),
  ADD KEY `product_units_barcode_index` (`barcode`);

--
-- Indexes for table `product_variants`
--
ALTER TABLE `product_variants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_variants_sku_unique` (`sku`),
  ADD KEY `product_variants_product_id_foreign` (`product_id`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `purchase_orders_order_number_unique` (`order_number`),
  ADD KEY `purchase_orders_supplier_id_foreign` (`supplier_id`),
  ADD KEY `purchase_orders_created_by_foreign` (`created_by`);

--
-- Indexes for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_order_items_purchase_order_id_foreign` (`purchase_order_id`),
  ADD KEY `purchase_order_items_product_id_foreign` (`product_id`);

--
-- Indexes for table `purchase_receipts`
--
ALTER TABLE `purchase_receipts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `purchase_receipts_receipt_number_unique` (`receipt_number`),
  ADD KEY `purchase_receipts_purchase_order_id_foreign` (`purchase_order_id`),
  ADD KEY `purchase_receipts_supplier_id_foreign` (`supplier_id`),
  ADD KEY `purchase_receipts_created_by_foreign` (`created_by`);

--
-- Indexes for table `purchase_receipt_items`
--
ALTER TABLE `purchase_receipt_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_receipt_items_purchase_receipt_id_foreign` (`purchase_receipt_id`),
  ADD KEY `purchase_receipt_items_product_id_foreign` (`product_id`);

--
-- Indexes for table `quotes`
--
ALTER TABLE `quotes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `quotes_quote_number_unique` (`quote_number`),
  ADD KEY `quotes_customer_id_foreign` (`customer_id`),
  ADD KEY `quotes_created_by_foreign` (`created_by`);

--
-- Indexes for table `quote_items`
--
ALTER TABLE `quote_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `quote_items_quote_id_foreign` (`quote_id`),
  ADD KEY `quote_items_product_id_foreign` (`product_id`);

--
-- Indexes for table `reorder_alerts`
--
ALTER TABLE `reorder_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reorder_alerts_warehouse_id_foreign` (`warehouse_id`),
  ADD KEY `reorder_alerts_product_variant_id_foreign` (`product_variant_id`),
  ADD KEY `reorder_alerts_resolved_by_foreign` (`resolved_by`),
  ADD KEY `reorder_alerts_product_id_warehouse_id_index` (`product_id`,`warehouse_id`),
  ADD KEY `reorder_alerts_status_index` (`status`),
  ADD KEY `reorder_alerts_severity_index` (`severity`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reports_type_is_public_index` (`type`,`is_public`),
  ADD KEY `reports_created_by_index` (`created_by`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reviews_user_id_product_id_unique` (`user_id`,`product_id`),
  ADD KEY `reviews_product_id_foreign` (`product_id`);

--
-- Indexes for table `rma_items`
--
ALTER TABLE `rma_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `rma_items_sales_order_item_id_foreign` (`sales_order_item_id`),
  ADD KEY `rma_items_product_variant_id_foreign` (`product_variant_id`),
  ADD KEY `rma_items_exchange_product_id_foreign` (`exchange_product_id`),
  ADD KEY `rma_items_exchange_variant_id_foreign` (`exchange_variant_id`),
  ADD KEY `rma_items_rma_request_id_index` (`rma_request_id`),
  ADD KEY `rma_items_product_id_index` (`product_id`);

--
-- Indexes for table `rma_requests`
--
ALTER TABLE `rma_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `rma_requests_rma_number_unique` (`rma_number`),
  ADD KEY `rma_requests_approved_by_foreign` (`approved_by`),
  ADD KEY `rma_requests_completed_by_foreign` (`completed_by`),
  ADD KEY `rma_requests_status_created_at_index` (`status`,`created_at`),
  ADD KEY `rma_requests_customer_id_index` (`customer_id`),
  ADD KEY `rma_requests_sales_order_id_index` (`sales_order_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `roles_name_unique` (`name`);

--
-- Indexes for table `role_user`
--
ALTER TABLE `role_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_user_role_id_user_id_unique` (`role_id`,`user_id`),
  ADD KEY `role_user_user_id_foreign` (`user_id`);

--
-- Indexes for table `sales_contracts`
--
ALTER TABLE `sales_contracts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sales_contracts_contract_number_unique` (`contract_number`),
  ADD KEY `sales_contracts_created_by_foreign` (`created_by`),
  ADD KEY `sales_contracts_approved_by_foreign` (`approved_by`),
  ADD KEY `sales_contracts_customer_id_status_index` (`customer_id`,`status`),
  ADD KEY `sales_contracts_status_index` (`status`);

--
-- Indexes for table `sales_orders`
--
ALTER TABLE `sales_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sales_orders_order_number_unique` (`order_number`),
  ADD KEY `sales_orders_customer_id_foreign` (`customer_id`),
  ADD KEY `sales_orders_quote_id_foreign` (`quote_id`),
  ADD KEY `sales_orders_created_by_foreign` (`created_by`),
  ADD KEY `sales_orders_channel_id_foreign` (`channel_id`),
  ADD KEY `sales_orders_contract_id_foreign` (`contract_id`),
  ADD KEY `sales_orders_fulfillment_warehouse_id_foreign` (`fulfillment_warehouse_id`),
  ADD KEY `sales_orders_external_order_id_index` (`external_order_id`);

--
-- Indexes for table `sales_order_items`
--
ALTER TABLE `sales_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sales_order_items_sales_order_id_foreign` (`sales_order_id`),
  ADD KEY `sales_order_items_product_id_foreign` (`product_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `settings_key_unique` (`key`);

--
-- Indexes for table `shipping_manifests`
--
ALTER TABLE `shipping_manifests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `shipping_manifests_manifest_number_unique` (`manifest_number`),
  ADD KEY `shipping_manifests_driver_id_foreign` (`driver_id`),
  ADD KEY `shipping_manifests_warehouse_id_status_index` (`warehouse_id`,`status`),
  ADD KEY `shipping_manifests_shipping_date_index` (`shipping_date`),
  ADD KEY `shipping_manifests_carrier_id_index` (`carrier_id`);

--
-- Indexes for table `shipping_manifest_items`
--
ALTER TABLE `shipping_manifest_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `shipping_manifest_items_packing_list_id_foreign` (`packing_list_id`),
  ADD KEY `shipping_manifest_items_shipping_manifest_id_index` (`shipping_manifest_id`),
  ADD KEY `shipping_manifest_items_sales_order_id_index` (`sales_order_id`);

--
-- Indexes for table `site_visitors`
--
ALTER TABLE `site_visitors`
  ADD PRIMARY KEY (`id`,`visit_date`),
  ADD UNIQUE KEY `site_visitors_ip_date_unique` (`ip_address`,`visit_date`),
  ADD KEY `site_visitors_visit_date_index` (`visit_date`);

--
-- Indexes for table `special_offers`
--
ALTER TABLE `special_offers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `special_offers_product_id_foreign` (`product_id`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stock_movements_product_id_foreign` (`product_id`),
  ADD KEY `stock_movements_created_by_foreign` (`created_by`),
  ADD KEY `stock_movements_warehouse_id_foreign` (`warehouse_id`);

--
-- Indexes for table `subscribers`
--
ALTER TABLE `subscribers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tickets_customer_id_foreign` (`customer_id`),
  ADD KEY `tickets_assigned_to_foreign` (`assigned_to`),
  ADD KEY `tickets_created_by_foreign` (`created_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD KEY `users_role_id_foreign` (`role_id`);

--
-- Indexes for table `visitors`
--
ALTER TABLE `visitors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `visitors_ip_address_visited_at_index` (`ip_address`,`visited_at`),
  ADD KEY `visitors_visited_at_index` (`visited_at`);

--
-- Indexes for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `wallet_transactions_user_id_foreign` (`user_id`);

--
-- Indexes for table `warehouses`
--
ALTER TABLE `warehouses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `warehouses_code_unique` (`code`),
  ADD KEY `warehouses_manager_id_foreign` (`manager_id`);

--
-- Indexes for table `warehouse_bins`
--
ALTER TABLE `warehouse_bins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `warehouse_bins_bin_code_unique` (`bin_code`),
  ADD KEY `warehouse_bins_warehouse_id_foreign` (`warehouse_id`);

--
-- Indexes for table `warehouse_inventory`
--
ALTER TABLE `warehouse_inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `wh_prod_var_unique` (`warehouse_id`,`product_id`,`product_variant_id`),
  ADD KEY `warehouse_inventory_product_id_foreign` (`product_id`),
  ADD KEY `warehouse_inventory_product_variant_id_foreign` (`product_variant_id`),
  ADD KEY `warehouse_inventory_bin_id_foreign` (`bin_id`);

--
-- Indexes for table `wishlist_items`
--
ALTER TABLE `wishlist_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `wishlist_items_user_id_product_id_unique` (`user_id`,`product_id`),
  ADD KEY `wishlist_items_product_id_foreign` (`product_id`);

--
-- Indexes for table `workflows`
--
ALTER TABLE `workflows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `workflows_created_by_foreign` (`created_by`),
  ADD KEY `workflows_trigger_type_index` (`trigger_type`),
  ADD KEY `workflows_status_index` (`status`);

--
-- Indexes for table `workflow_executions`
--
ALTER TABLE `workflow_executions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `workflow_executions_triggered_by_foreign` (`triggered_by`),
  ADD KEY `workflow_executions_workflow_id_index` (`workflow_id`),
  ADD KEY `workflow_executions_status_index` (`status`),
  ADD KEY `workflow_executions_entity_type_entity_id_index` (`entity_type`,`entity_id`),
  ADD KEY `workflow_executions_entity_id_index` (`entity_id`);

--
-- Indexes for table `workflow_steps`
--
ALTER TABLE `workflow_steps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `workflow_steps_assigned_to_foreign` (`assigned_to`),
  ADD KEY `workflow_steps_workflow_id_index` (`workflow_id`),
  ADD KEY `workflow_steps_order_index` (`order`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `addresses`
--
ALTER TABLE `addresses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `analytics_data_points`
--
ALTER TABLE `analytics_data_points`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `analytics_metrics`
--
ALTER TABLE `analytics_metrics`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendances`
--
ALTER TABLE `attendances`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `carts`
--
ALTER TABLE `carts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contacts`
--
ALTER TABLE `contacts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cycle_counts`
--
ALTER TABLE `cycle_counts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cycle_count_items`
--
ALTER TABLE `cycle_count_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dashboards`
--
ALTER TABLE `dashboards`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dashboard_widgets`
--
ALTER TABLE `dashboard_widgets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inquiries`
--
ALTER TABLE `inquiries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `inquiry_replies`
--
ALTER TABLE `inquiry_replies`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `integration_settings`
--
ALTER TABLE `integration_settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_transfers`
--
ALTER TABLE `inventory_transfers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_transfer_items`
--
ALTER TABLE `inventory_transfer_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoice_items`
--
ALTER TABLE `invoice_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `journal_entries`
--
ALTER TABLE `journal_entries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `landed_costs`
--
ALTER TABLE `landed_costs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ledger_accounts`
--
ALTER TABLE `ledger_accounts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=112;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification_preferences`
--
ALTER TABLE `notification_preferences`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification_templates`
--
ALTER TABLE `notification_templates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_channels`
--
ALTER TABLE `order_channels`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `packing_lists`
--
ALTER TABLE `packing_lists`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `packing_list_items`
--
ALTER TABLE `packing_list_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payrolls`
--
ALTER TABLE `payrolls`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `permission_role`
--
ALTER TABLE `permission_role`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `picking_lists`
--
ALTER TABLE `picking_lists`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `picking_list_items`
--
ALTER TABLE `picking_list_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `production_orders`
--
ALTER TABLE `production_orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=107;

--
-- AUTO_INCREMENT for table `product_batches`
--
ALTER TABLE `product_batches`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_serial_numbers`
--
ALTER TABLE `product_serial_numbers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_units`
--
ALTER TABLE `product_units`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_variants`
--
ALTER TABLE `product_variants`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_receipts`
--
ALTER TABLE `purchase_receipts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_receipt_items`
--
ALTER TABLE `purchase_receipt_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quotes`
--
ALTER TABLE `quotes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quote_items`
--
ALTER TABLE `quote_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reorder_alerts`
--
ALTER TABLE `reorder_alerts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rma_items`
--
ALTER TABLE `rma_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rma_requests`
--
ALTER TABLE `rma_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `role_user`
--
ALTER TABLE `role_user`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales_contracts`
--
ALTER TABLE `sales_contracts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales_orders`
--
ALTER TABLE `sales_orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales_order_items`
--
ALTER TABLE `sales_order_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=149;

--
-- AUTO_INCREMENT for table `shipping_manifests`
--
ALTER TABLE `shipping_manifests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shipping_manifest_items`
--
ALTER TABLE `shipping_manifest_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `site_visitors`
--
ALTER TABLE `site_visitors`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `special_offers`
--
ALTER TABLE `special_offers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subscribers`
--
ALTER TABLE `subscribers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `visitors`
--
ALTER TABLE `visitors`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=873;

--
-- AUTO_INCREMENT for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `warehouses`
--
ALTER TABLE `warehouses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `warehouse_bins`
--
ALTER TABLE `warehouse_bins`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `warehouse_inventory`
--
ALTER TABLE `warehouse_inventory`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wishlist_items`
--
ALTER TABLE `wishlist_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `workflows`
--
ALTER TABLE `workflows`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `workflow_executions`
--
ALTER TABLE `workflow_executions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `workflow_steps`
--
ALTER TABLE `workflow_steps`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- قيود الجداول المُلقاة.
--

--
-- قيود الجداول `addresses`
--
ALTER TABLE `addresses`
  ADD CONSTRAINT `addresses_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `analytics_data_points`
--
ALTER TABLE `analytics_data_points`
  ADD CONSTRAINT `analytics_data_points_channel_id_foreign` FOREIGN KEY (`channel_id`) REFERENCES `order_channels` (`id`),
  ADD CONSTRAINT `analytics_data_points_metric_id_foreign` FOREIGN KEY (`metric_id`) REFERENCES `analytics_metrics` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `analytics_data_points_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`);

--
-- قيود الجداول `attendances`
--
ALTER TABLE `attendances`
  ADD CONSTRAINT `attendances_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- قيود الجداول `carts`
--
ALTER TABLE `carts`
  ADD CONSTRAINT `carts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `cart_items_cart_id_foreign` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- قيود الجداول `cycle_counts`
--
ALTER TABLE `cycle_counts`
  ADD CONSTRAINT `cycle_counts_adjustment_by_foreign` FOREIGN KEY (`adjustment_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `cycle_counts_bin_id_foreign` FOREIGN KEY (`bin_id`) REFERENCES `warehouse_bins` (`id`),
  ADD CONSTRAINT `cycle_counts_counter_id_foreign` FOREIGN KEY (`counter_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `cycle_counts_reviewer_id_foreign` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `cycle_counts_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `cycle_count_items`
--
ALTER TABLE `cycle_count_items`
  ADD CONSTRAINT `cycle_count_items_bin_id_foreign` FOREIGN KEY (`bin_id`) REFERENCES `warehouse_bins` (`id`),
  ADD CONSTRAINT `cycle_count_items_cycle_count_id_foreign` FOREIGN KEY (`cycle_count_id`) REFERENCES `cycle_counts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cycle_count_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `cycle_count_items_product_variant_id_foreign` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`);

--
-- قيود الجداول `dashboards`
--
ALTER TABLE `dashboards`
  ADD CONSTRAINT `dashboards_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- قيود الجداول `dashboard_widgets`
--
ALTER TABLE `dashboard_widgets`
  ADD CONSTRAINT `dashboard_widgets_dashboard_id_foreign` FOREIGN KEY (`dashboard_id`) REFERENCES `dashboards` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `dashboard_widgets_metric_id_foreign` FOREIGN KEY (`metric_id`) REFERENCES `analytics_metrics` (`id`),
  ADD CONSTRAINT `dashboard_widgets_report_id_foreign` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`);

--
-- قيود الجداول `inquiries`
--
ALTER TABLE `inquiries`
  ADD CONSTRAINT `inquiries_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inquiries_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inquiries_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- قيود الجداول `inquiry_replies`
--
ALTER TABLE `inquiry_replies`
  ADD CONSTRAINT `inquiry_replies_admin_id_foreign` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inquiry_replies_inquiry_id_foreign` FOREIGN KEY (`inquiry_id`) REFERENCES `inquiries` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `inventory_transfers`
--
ALTER TABLE `inventory_transfers`
  ADD CONSTRAINT `inventory_transfers_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `inventory_transfers_from_warehouse_id_foreign` FOREIGN KEY (`from_warehouse_id`) REFERENCES `warehouses` (`id`),
  ADD CONSTRAINT `inventory_transfers_received_by_foreign` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `inventory_transfers_shipped_by_foreign` FOREIGN KEY (`shipped_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `inventory_transfers_to_warehouse_id_foreign` FOREIGN KEY (`to_warehouse_id`) REFERENCES `warehouses` (`id`);

--
-- قيود الجداول `inventory_transfer_items`
--
ALTER TABLE `inventory_transfer_items`
  ADD CONSTRAINT `inventory_transfer_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `inventory_transfer_items_product_variant_id_foreign` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`),
  ADD CONSTRAINT `inventory_transfer_items_transfer_id_foreign` FOREIGN KEY (`transfer_id`) REFERENCES `inventory_transfers` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `invoices_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `invoices_sales_order_id_foreign` FOREIGN KEY (`sales_order_id`) REFERENCES `sales_orders` (`id`) ON DELETE SET NULL;

--
-- قيود الجداول `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD CONSTRAINT `invoice_items_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `invoice_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `invoice_items_product_unit_id_foreign` FOREIGN KEY (`product_unit_id`) REFERENCES `product_units` (`id`) ON DELETE SET NULL;

--
-- قيود الجداول `journal_entries`
--
ALTER TABLE `journal_entries`
  ADD CONSTRAINT `journal_entries_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `journal_entries_ledger_account_id_foreign` FOREIGN KEY (`ledger_account_id`) REFERENCES `ledger_accounts` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `landed_costs`
--
ALTER TABLE `landed_costs`
  ADD CONSTRAINT `landed_costs_purchase_receipt_id_foreign` FOREIGN KEY (`purchase_receipt_id`) REFERENCES `purchase_receipts` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `leave_requests_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `leave_requests_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `notification_preferences`
--
ALTER TABLE `notification_preferences`
  ADD CONSTRAINT `notification_preferences_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_payment_method_id_foreign` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_shipping_address_id_foreign` FOREIGN KEY (`shipping_address_id`) REFERENCES `addresses` (`id`),
  ADD CONSTRAINT `orders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- قيود الجداول `packing_lists`
--
ALTER TABLE `packing_lists`
  ADD CONSTRAINT `packing_lists_packer_id_foreign` FOREIGN KEY (`packer_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `packing_lists_picking_list_id_foreign` FOREIGN KEY (`picking_list_id`) REFERENCES `picking_lists` (`id`),
  ADD CONSTRAINT `packing_lists_sales_order_id_foreign` FOREIGN KEY (`sales_order_id`) REFERENCES `sales_orders` (`id`),
  ADD CONSTRAINT `packing_lists_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `packing_list_items`
--
ALTER TABLE `packing_list_items`
  ADD CONSTRAINT `packing_list_items_packing_list_id_foreign` FOREIGN KEY (`packing_list_id`) REFERENCES `packing_lists` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `packing_list_items_picking_list_item_id_foreign` FOREIGN KEY (`picking_list_item_id`) REFERENCES `picking_list_items` (`id`),
  ADD CONSTRAINT `packing_list_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `packing_list_items_product_variant_id_foreign` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`);

--
-- قيود الجداول `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `payments_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `payments_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `payments_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `payments_sales_order_id_foreign` FOREIGN KEY (`sales_order_id`) REFERENCES `sales_orders` (`id`) ON DELETE SET NULL;

--
-- قيود الجداول `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD CONSTRAINT `payment_methods_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `payrolls`
--
ALTER TABLE `payrolls`
  ADD CONSTRAINT `payrolls_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `payrolls_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL;

--
-- قيود الجداول `permission_role`
--
ALTER TABLE `permission_role`
  ADD CONSTRAINT `permission_role_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `permission_role_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `picking_lists`
--
ALTER TABLE `picking_lists`
  ADD CONSTRAINT `picking_lists_picker_id_foreign` FOREIGN KEY (`picker_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `picking_lists_sales_order_id_foreign` FOREIGN KEY (`sales_order_id`) REFERENCES `sales_orders` (`id`),
  ADD CONSTRAINT `picking_lists_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `picking_list_items`
--
ALTER TABLE `picking_list_items`
  ADD CONSTRAINT `picking_list_items_bin_id_foreign` FOREIGN KEY (`bin_id`) REFERENCES `warehouse_bins` (`id`),
  ADD CONSTRAINT `picking_list_items_picking_list_id_foreign` FOREIGN KEY (`picking_list_id`) REFERENCES `picking_lists` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `picking_list_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `picking_list_items_product_variant_id_foreign` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`),
  ADD CONSTRAINT `picking_list_items_sales_order_item_id_foreign` FOREIGN KEY (`sales_order_item_id`) REFERENCES `sales_order_items` (`id`);

--
-- قيود الجداول `production_orders`
--
ALTER TABLE `production_orders`
  ADD CONSTRAINT `production_orders_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `production_orders_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- قيود الجداول `product_batches`
--
ALTER TABLE `product_batches`
  ADD CONSTRAINT `product_batches_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `product_batches_product_variant_id_foreign` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`),
  ADD CONSTRAINT `product_batches_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`);

--
-- قيود الجداول `product_serial_numbers`
--
ALTER TABLE `product_serial_numbers`
  ADD CONSTRAINT `product_serial_numbers_batch_id_foreign` FOREIGN KEY (`batch_id`) REFERENCES `product_batches` (`id`),
  ADD CONSTRAINT `product_serial_numbers_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `product_serial_numbers_product_variant_id_foreign` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`),
  ADD CONSTRAINT `product_serial_numbers_sale_order_id_foreign` FOREIGN KEY (`sale_order_id`) REFERENCES `sales_orders` (`id`),
  ADD CONSTRAINT `product_serial_numbers_sale_order_item_id_foreign` FOREIGN KEY (`sale_order_item_id`) REFERENCES `sales_order_items` (`id`),
  ADD CONSTRAINT `product_serial_numbers_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`);

--
-- قيود الجداول `product_units`
--
ALTER TABLE `product_units`
  ADD CONSTRAINT `product_units_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `product_variants`
--
ALTER TABLE `product_variants`
  ADD CONSTRAINT `product_variants_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `purchase_orders_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `purchase_orders_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD CONSTRAINT `purchase_order_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `purchase_order_items_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `purchase_receipts`
--
ALTER TABLE `purchase_receipts`
  ADD CONSTRAINT `purchase_receipts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `purchase_receipts_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `purchase_receipts_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL;

--
-- قيود الجداول `purchase_receipt_items`
--
ALTER TABLE `purchase_receipt_items`
  ADD CONSTRAINT `purchase_receipt_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `purchase_receipt_items_purchase_receipt_id_foreign` FOREIGN KEY (`purchase_receipt_id`) REFERENCES `purchase_receipts` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `quotes`
--
ALTER TABLE `quotes`
  ADD CONSTRAINT `quotes_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `quotes_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL;

--
-- قيود الجداول `quote_items`
--
ALTER TABLE `quote_items`
  ADD CONSTRAINT `quote_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `quote_items_quote_id_foreign` FOREIGN KEY (`quote_id`) REFERENCES `quotes` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `reorder_alerts`
--
ALTER TABLE `reorder_alerts`
  ADD CONSTRAINT `reorder_alerts_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `reorder_alerts_product_variant_id_foreign` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`),
  ADD CONSTRAINT `reorder_alerts_resolved_by_foreign` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `reorder_alerts_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`);

--
-- قيود الجداول `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `reports_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- قيود الجداول `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `rma_items`
--
ALTER TABLE `rma_items`
  ADD CONSTRAINT `rma_items_exchange_product_id_foreign` FOREIGN KEY (`exchange_product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `rma_items_exchange_variant_id_foreign` FOREIGN KEY (`exchange_variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `rma_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `rma_items_product_variant_id_foreign` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `rma_items_rma_request_id_foreign` FOREIGN KEY (`rma_request_id`) REFERENCES `rma_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `rma_items_sales_order_item_id_foreign` FOREIGN KEY (`sales_order_item_id`) REFERENCES `sales_order_items` (`id`) ON DELETE SET NULL;

--
-- قيود الجداول `rma_requests`
--
ALTER TABLE `rma_requests`
  ADD CONSTRAINT `rma_requests_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `rma_requests_completed_by_foreign` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `rma_requests_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `rma_requests_sales_order_id_foreign` FOREIGN KEY (`sales_order_id`) REFERENCES `sales_orders` (`id`) ON DELETE SET NULL;

--
-- قيود الجداول `role_user`
--
ALTER TABLE `role_user`
  ADD CONSTRAINT `role_user_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `sales_contracts`
--
ALTER TABLE `sales_contracts`
  ADD CONSTRAINT `sales_contracts_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `sales_contracts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `sales_contracts_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`);

--
-- قيود الجداول `sales_orders`
--
ALTER TABLE `sales_orders`
  ADD CONSTRAINT `sales_orders_channel_id_foreign` FOREIGN KEY (`channel_id`) REFERENCES `order_channels` (`id`),
  ADD CONSTRAINT `sales_orders_contract_id_foreign` FOREIGN KEY (`contract_id`) REFERENCES `sales_contracts` (`id`),
  ADD CONSTRAINT `sales_orders_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sales_orders_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sales_orders_fulfillment_warehouse_id_foreign` FOREIGN KEY (`fulfillment_warehouse_id`) REFERENCES `warehouses` (`id`),
  ADD CONSTRAINT `sales_orders_quote_id_foreign` FOREIGN KEY (`quote_id`) REFERENCES `quotes` (`id`) ON DELETE SET NULL;

--
-- قيود الجداول `sales_order_items`
--
ALTER TABLE `sales_order_items`
  ADD CONSTRAINT `sales_order_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sales_order_items_sales_order_id_foreign` FOREIGN KEY (`sales_order_id`) REFERENCES `sales_orders` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `shipping_manifests`
--
ALTER TABLE `shipping_manifests`
  ADD CONSTRAINT `shipping_manifests_driver_id_foreign` FOREIGN KEY (`driver_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `shipping_manifests_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `shipping_manifest_items`
--
ALTER TABLE `shipping_manifest_items`
  ADD CONSTRAINT `shipping_manifest_items_packing_list_id_foreign` FOREIGN KEY (`packing_list_id`) REFERENCES `packing_lists` (`id`),
  ADD CONSTRAINT `shipping_manifest_items_sales_order_id_foreign` FOREIGN KEY (`sales_order_id`) REFERENCES `sales_orders` (`id`),
  ADD CONSTRAINT `shipping_manifest_items_shipping_manifest_id_foreign` FOREIGN KEY (`shipping_manifest_id`) REFERENCES `shipping_manifests` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `special_offers`
--
ALTER TABLE `special_offers`
  ADD CONSTRAINT `special_offers_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL;

--
-- قيود الجداول `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `stock_movements_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `stock_movements_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_movements_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL;

--
-- قيود الجداول `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `tickets_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tickets_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tickets_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL;

--
-- قيود الجداول `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD CONSTRAINT `wallet_transactions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `warehouses`
--
ALTER TABLE `warehouses`
  ADD CONSTRAINT `warehouses_manager_id_foreign` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`);

--
-- قيود الجداول `warehouse_bins`
--
ALTER TABLE `warehouse_bins`
  ADD CONSTRAINT `warehouse_bins_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `warehouse_inventory`
--
ALTER TABLE `warehouse_inventory`
  ADD CONSTRAINT `warehouse_inventory_bin_id_foreign` FOREIGN KEY (`bin_id`) REFERENCES `warehouse_bins` (`id`),
  ADD CONSTRAINT `warehouse_inventory_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `warehouse_inventory_product_variant_id_foreign` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `warehouse_inventory_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `wishlist_items`
--
ALTER TABLE `wishlist_items`
  ADD CONSTRAINT `wishlist_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `wishlist_items_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `workflows`
--
ALTER TABLE `workflows`
  ADD CONSTRAINT `workflows_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- قيود الجداول `workflow_executions`
--
ALTER TABLE `workflow_executions`
  ADD CONSTRAINT `workflow_executions_triggered_by_foreign` FOREIGN KEY (`triggered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `workflow_executions_workflow_id_foreign` FOREIGN KEY (`workflow_id`) REFERENCES `workflows` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `workflow_steps`
--
ALTER TABLE `workflow_steps`
  ADD CONSTRAINT `workflow_steps_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `workflow_steps_workflow_id_foreign` FOREIGN KEY (`workflow_id`) REFERENCES `workflows` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
