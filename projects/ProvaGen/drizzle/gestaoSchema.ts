import { bigint, boolean, int, mysqlTable, timestamp, varchar } from "drizzle-orm/mysql-core";

/**
 * Definições de leitura para tabelas do módulo de gestão (Laravel, em
 * D:\ProvaGen\gestao — código fonte separado deste app). NUNCA rodar
 * migrations/drizzle-kit contra este arquivo: essas tabelas são criadas e
 * versionadas pelas migrations do Laravel, não por este schema — ele existe
 * só para permitir queries tipadas de leitura (e a importação de
 * turma/aluno em server/_core/gestaoImport.ts), já que os dois apps
 * compartilham o mesmo banco físico.
 */

export const gestaoClassGroups = mysqlTable("class_groups", {
  id: bigint("id", { mode: "number" }).autoincrement().primaryKey(),
  ownerUserId: int("owner_user_id"),
  name: varchar("name", { length: 255 }).notNull(),
  semester: varchar("semester", { length: 255 }).notNull(),
  shift: varchar("shift", { length: 255 }),
  active: boolean("active").notNull(),
  createdAt: timestamp("created_at"),
  updatedAt: timestamp("updated_at"),
});

export const gestaoAdvisees = mysqlTable("advisees", {
  id: bigint("id", { mode: "number" }).autoincrement().primaryKey(),
  ownerUserId: int("owner_user_id"),
  userId: bigint("user_id", { mode: "number" }).notNull(),
  classGroupId: bigint("class_group_id", { mode: "number" }),
  registration: varchar("registration", { length: 255 }),
  status: varchar("status", { length: 255 }),
});

export const gestaoAdviseeAccounts = mysqlTable("advisee_accounts", {
  id: bigint("id", { mode: "number" }).autoincrement().primaryKey(),
  ownerUserId: int("owner_user_id").notNull(),
  name: varchar("name", { length: 255 }).notNull(),
  email: varchar("email", { length: 255 }).notNull(),
});
