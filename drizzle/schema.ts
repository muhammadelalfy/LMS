import { bigint, int, mysqlEnum, mysqlTable, text, timestamp, varchar } from "drizzle-orm/mysql-core";

export const users = mysqlTable("users", {
  id: int("id").autoincrement().primaryKey(),
  openId: varchar("openId", { length: 64 }).notNull().unique(),
  name: text("name"),
  email: varchar("email", { length: 320 }),
  loginMethod: varchar("loginMethod", { length: 64 }),
  role: mysqlEnum("role", ["user", "admin", "teacher", "parent", "student"]).default("user").notNull(),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
  lastSignedIn: timestamp("lastSignedIn").defaultNow().notNull(),
});

export const students = mysqlTable("students", {
  id: int("id").autoincrement().primaryKey(),
  name: varchar("name", { length: 160 }).notNull(),
  group: varchar("group", { length: 32 }).notNull(),
  grade: varchar("grade", { length: 80 }).notNull(),
  phone: varchar("phone", { length: 32 }).notNull(),
  parentPhone: varchar("parentPhone", { length: 32 }),
  status: mysqlEnum("status", ["excellent", "average", "weak"]).default("average").notNull(),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
});

export const studentAccounts = mysqlTable("studentAccounts", {
  id: int("id").autoincrement().primaryKey(),
  studentId: int("studentId").notNull(),
  userId: int("userId").notNull().unique(),
  relationship: mysqlEnum("relationship", ["student", "parent"]).notNull(),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
});

export const worksheets = mysqlTable("worksheets", {
  id: int("id").autoincrement().primaryKey(),
  title: varchar("title", { length: 180 }).notNull(),
  subject: varchar("subject", { length: 80 }).notNull(),
  grade: varchar("grade", { length: 80 }).notNull(),
  instructions: text("instructions"),
  dueAt: bigint("dueAt", { mode: "number" }),
  status: mysqlEnum("status", ["draft", "published"]).default("draft").notNull(),
  createdBy: int("createdBy").notNull(),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
});

export const worksheetAssignments = mysqlTable("worksheetAssignments", {
  id: int("id").autoincrement().primaryKey(),
  worksheetId: int("worksheetId").notNull(),
  studentId: int("studentId").notNull(),
  status: mysqlEnum("status", ["assigned", "in_progress", "submitted", "graded"]).default("assigned").notNull(),
  assignedAt: bigint("assignedAt", { mode: "number" }).notNull(),
  submittedAt: bigint("submittedAt", { mode: "number" }),
  score: int("score"),
  maxScore: int("maxScore"),
  feedback: text("feedback"),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
});

export const attendanceRecords = mysqlTable("attendanceRecords", {
  id: int("id").autoincrement().primaryKey(),
  studentId: int("studentId").notNull(),
  dateAt: bigint("dateAt", { mode: "number" }).notNull(),
  status: mysqlEnum("status", ["present", "absent", "late"]).notNull(),
  note: text("note"),
  recordedBy: int("recordedBy").notNull(),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
});

export const examResults = mysqlTable("examResults", {
  id: int("id").autoincrement().primaryKey(),
  studentId: int("studentId").notNull(),
  title: varchar("title", { length: 180 }).notNull(),
  score: int("score").notNull(),
  maxScore: int("maxScore").notNull(),
  takenAt: bigint("takenAt", { mode: "number" }).notNull(),
  recordedBy: int("recordedBy").notNull(),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
});

export const payments = mysqlTable("payments", {
  id: int("id").autoincrement().primaryKey(),
  studentId: int("studentId").notNull(),
  amount: int("amount").notNull(),
  status: mysqlEnum("status", ["pending", "paid", "overdue"]).default("pending").notNull(),
  dueAt: bigint("dueAt", { mode: "number" }).notNull(),
  paidAt: bigint("paidAt", { mode: "number" }),
  note: text("note"),
  recordedBy: int("recordedBy").notNull(),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
});

export type User = typeof users.$inferSelect;
export type InsertUser = typeof users.$inferInsert;
export type Student = typeof students.$inferSelect;
export type InsertStudent = typeof students.$inferInsert;
export type Worksheet = typeof worksheets.$inferSelect;
export type InsertWorksheet = typeof worksheets.$inferInsert;
export type WorksheetAssignment = typeof worksheetAssignments.$inferSelect;
export type InsertWorksheetAssignment = typeof worksheetAssignments.$inferInsert;
export type AttendanceRecord = typeof attendanceRecords.$inferSelect;
export type InsertAttendanceRecord = typeof attendanceRecords.$inferInsert;
export type ExamResult = typeof examResults.$inferSelect;
export type InsertExamResult = typeof examResults.$inferInsert;
export type Payment = typeof payments.$inferSelect;
export type InsertPayment = typeof payments.$inferInsert;
