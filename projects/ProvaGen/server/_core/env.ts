export const ENV = {
  appId: process.env.VITE_APP_ID ?? "",
  cookieSecret: process.env.JWT_SECRET ?? "",
  databaseUrl: process.env.DATABASE_URL ?? "",
  oAuthServerUrl: process.env.OAUTH_SERVER_URL ?? "",
  ownerOpenId: process.env.OWNER_OPEN_ID ?? "",
  isProduction: process.env.NODE_ENV === "production",
  forgeApiUrl: process.env.BUILT_IN_FORGE_API_URL ?? "",
  forgeApiKey: process.env.BUILT_IN_FORGE_API_KEY ?? "",
  // Base site URL used for SEO (robots, sitemap, canonical links)
  siteBase: process.env.SITE_BASE ?? "https://www.prof-aid.com",
  // Google OAuth Configuration
  googleClientId: process.env.GOOGLE_CLIENT_ID ?? "",
  googleClientSecret: process.env.GOOGLE_CLIENT_SECRET ?? "",
  googleCallbackUrl: process.env.GOOGLE_CALLBACK_URL ?? "http://localhost:3000/api/auth/google/callback",
  // AWS S3 / Cloudflare R2 Configuration
  awsAccessKeyId: process.env.AWS_ACCESS_KEY_ID ?? "",
  awsSecretAccessKey: process.env.AWS_SECRET_ACCESS_KEY ?? "",
  awsS3Bucket: process.env.AWS_S3_BUCKET ?? "",
  awsRegion: process.env.AWS_REGION ?? "us-east-1",
  awsS3Endpoint: process.env.AWS_S3_ENDPOINT ?? "",
  // Módulo de gestão de alunos (Laravel, D:\ProvaGen\gestao) — SSO
  gestaoSsoSecret: process.env.GESTAO_SSO_SECRET ?? "",
  gestaoBaseUrl: process.env.GESTAO_BASE_URL ?? "http://localhost:8000",
};
