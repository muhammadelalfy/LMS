CREATE TABLE `attendanceRecords` (
	`id` int AUTO_INCREMENT NOT NULL,
	`studentId` int NOT NULL,
	`dateAt` bigint NOT NULL,
	`status` enum('present','absent','late') NOT NULL,
	`note` text,
	`recordedBy` int NOT NULL,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	CONSTRAINT `attendanceRecords_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `examResults` (
	`id` int AUTO_INCREMENT NOT NULL,
	`studentId` int NOT NULL,
	`title` varchar(180) NOT NULL,
	`score` int NOT NULL,
	`maxScore` int NOT NULL,
	`takenAt` bigint NOT NULL,
	`recordedBy` int NOT NULL,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	CONSTRAINT `examResults_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `payments` (
	`id` int AUTO_INCREMENT NOT NULL,
	`studentId` int NOT NULL,
	`amount` int NOT NULL,
	`status` enum('pending','paid','overdue') NOT NULL DEFAULT 'pending',
	`dueAt` bigint NOT NULL,
	`paidAt` bigint,
	`note` text,
	`recordedBy` int NOT NULL,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `payments_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `studentAccounts` (
	`id` int AUTO_INCREMENT NOT NULL,
	`studentId` int NOT NULL,
	`userId` int NOT NULL,
	`relationship` enum('student','parent') NOT NULL,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	CONSTRAINT `studentAccounts_id` PRIMARY KEY(`id`),
	CONSTRAINT `studentAccounts_userId_unique` UNIQUE(`userId`)
);
--> statement-breakpoint
CREATE TABLE `students` (
	`id` int AUTO_INCREMENT NOT NULL,
	`name` varchar(160) NOT NULL,
	`group` varchar(32) NOT NULL,
	`grade` varchar(80) NOT NULL,
	`phone` varchar(32) NOT NULL,
	`parentPhone` varchar(32),
	`status` enum('excellent','average','weak') NOT NULL DEFAULT 'average',
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `students_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `users` (
	`id` int AUTO_INCREMENT NOT NULL,
	`openId` varchar(64) NOT NULL,
	`name` text,
	`email` varchar(320),
	`loginMethod` varchar(64),
	`role` enum('user','admin','teacher','parent','student') NOT NULL DEFAULT 'user',
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	`lastSignedIn` timestamp NOT NULL DEFAULT (now()),
	CONSTRAINT `users_id` PRIMARY KEY(`id`),
	CONSTRAINT `users_openId_unique` UNIQUE(`openId`)
);
--> statement-breakpoint
CREATE TABLE `worksheetAssignments` (
	`id` int AUTO_INCREMENT NOT NULL,
	`worksheetId` int NOT NULL,
	`studentId` int NOT NULL,
	`status` enum('assigned','in_progress','submitted','graded') NOT NULL DEFAULT 'assigned',
	`assignedAt` bigint NOT NULL,
	`submittedAt` bigint,
	`score` int,
	`maxScore` int,
	`feedback` text,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `worksheetAssignments_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `worksheets` (
	`id` int AUTO_INCREMENT NOT NULL,
	`title` varchar(180) NOT NULL,
	`subject` varchar(80) NOT NULL,
	`grade` varchar(80) NOT NULL,
	`instructions` text,
	`dueAt` bigint,
	`status` enum('draft','published') NOT NULL DEFAULT 'draft',
	`createdBy` int NOT NULL,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `worksheets_id` PRIMARY KEY(`id`)
);
