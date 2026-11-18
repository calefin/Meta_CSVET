import { describe, expect, it, vi } from "vitest";
import { appRouter } from "./routers";
import type { TrpcContext } from "./_core/context";

type AuthenticatedUser = NonNullable<TrpcContext["user"]>;

function createAuthContext(userId: number = 1): { ctx: TrpcContext } {
  const user: AuthenticatedUser = {
    id: userId,
    openId: `test-user-${userId}`,
    email: `user${userId}@example.com`,
    name: `Test User ${userId}`,
    loginMethod: "manus",
    role: "user",
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
    res: {
      clearCookie: vi.fn(),
    } as unknown as TrpcContext["res"],
  };

  return { ctx };
}

describe("profile", { timeout: 10000 }, () => {
  it("profile.me returns current user", async () => {
    const { ctx } = createAuthContext(1);
    const caller = appRouter.createCaller(ctx);

    const result = await caller.profile.me();

    expect(result.user).toBeDefined();
    expect(result.user?.id).toBe(1);
    expect(result.user?.email).toBe("user1@example.com");
  });

  it("profile.update accepts phone and university", async () => {
    const { ctx } = createAuthContext(1);
    const caller = appRouter.createCaller(ctx);

    const result = await caller.profile.update({
      phone: "(11) 99999-9999",
      university: "Universidade Federal de São Paulo",
    });

    expect(result.success).toBe(true);
  });

  it("profile.update handles partial updates", async () => {
    const { ctx } = createAuthContext(1);
    const caller = appRouter.createCaller(ctx);

    const result = await caller.profile.update({
      phone: "(11) 88888-8888",
    });

    expect(result.success).toBe(true);
  });
});
