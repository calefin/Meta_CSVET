import { describe, it, expect } from "vitest";
import { appRouter } from "./routers";
import type { TrpcContext } from "./_core/context";

describe("questions.import with authentication", () => {
  it("should import DOCX file when authenticated", async () => {
    // Criar contexto autenticado
    const user = {
      id: 1,
      openId: "test-user",
      email: "test@example.com",
      name: "Test User",
      loginMethod: "manus",
      role: "user" as const,
      createdAt: new Date(),
      updatedAt: new Date(),
      lastSignedIn: new Date(),
    };

    const ctx: TrpcContext = {
      user,
      req: {
        protocol: "https",
        headers: {},
      } as TrpcContext["req"],
      res: {} as TrpcContext["res"],
    };

    const caller = appRouter.createCaller(ctx);

    // Ler arquivo
    const fs = await import("fs").then((m) => m.promises);
    const testFilePath = "/home/ubuntu/upload/P2AFMATFisiologia.docx";
    if (!(await fs.stat(testFilePath).catch(() => false))) {
      console.log("Pulando teste DOCX autenticado - arquivo de fixture não disponível");
      return;
    }

    const fileBuffer = await fs.readFile(testFilePath);
    const base64Data = fileBuffer.toString("base64");

    // Chamar mutation
    const result = await caller.questions.import({
      fileName: "P2AFMATFisiologia.docx",
      fileData: base64Data,
      fileType: "docx",
    });

    console.log("Result:", JSON.stringify(result, null, 2));

    expect(result.success).toBe(true);
    expect(result.count).toBeGreaterThan(0);
    expect(result.questions).toBeInstanceOf(Array);
    expect(result.questions.length).toBe(8);
  });
});
