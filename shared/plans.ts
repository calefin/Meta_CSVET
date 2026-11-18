export const PLAN_KEYS = ["free", "basic", "pro", "enterprise"] as const;
export type PlanKey = (typeof PLAN_KEYS)[number];

export const PLAN_PRICES: Record<Exclude<PlanKey, "free">, number> = {
  basic:      29,
  pro:        59,
  enterprise: 149,
};

export const PLAN_NAMES: Record<PlanKey, string> = {
  free:       "Gratuito",
  basic:      "Basic",
  pro:        "Pro",
  enterprise: "Enterprise",
};

// -1 = unlimited
export const PLAN_LIMITS: Record<PlanKey, {
  maxQuestions: number;
  maxVersions:  number;
  storageMB:    number;
  sharingEnabled: boolean;
}> = {
  free:       { maxQuestions: 50,   maxVersions: 1,  storageMB: 100,   sharingEnabled: false },
  basic:      { maxQuestions: 500,  maxVersions: 5,  storageMB: 1024,  sharingEnabled: true  },
  pro:        { maxQuestions: -1,   maxVersions: 50, storageMB: 10240, sharingEnabled: true  },
  enterprise: { maxQuestions: -1,   maxVersions: 50, storageMB: -1,    sharingEnabled: true  },
};
