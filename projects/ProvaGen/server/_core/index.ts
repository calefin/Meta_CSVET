import "dotenv/config";
import express from "express";
import { createServer } from "http";
import net from "net";
import session from "express-session";
import { createExpressMiddleware } from "@trpc/server/adapters/express";
import passport from "passport";
import { initializePassport } from "./passport";
import { registerOAuthRoutes } from "./oauth";
import { registerGoogleAuthRoutes } from "./googleAuth";
import { registerStorageProxy } from "./storageProxy";
import { registerImageRoutes } from "./imageRoutes";
import { registerMobileApiRoutes } from "./mobileApi";
import { registerGestaoSsoRoutes } from "./gestaoSsoRoutes";
import { appRouter } from "../routers";
import { createContext } from "./context";
import { serveStatic, setupVite } from "./vite";
import { ENV } from "./env";
import QRCode from 'qrcode';

function isPortAvailable(port: number): Promise<boolean> {
  return new Promise(resolve => {
    const server = net.createServer();
    server.listen(port, () => {
      server.close(() => resolve(true));
    });
    server.on("error", () => resolve(false));
  });
}

async function findAvailablePort(startPort: number = 3000): Promise<number> {
  for (let port = startPort; port < startPort + 20; port++) {
    if (await isPortAvailable(port)) {
      return port;
    }
  }
  throw new Error(`No available port found starting from ${startPort}`);
}

async function startServer() {
  const app = express();
  const server = createServer(app);
  
  const preferredPort = parseInt(process.env.PORT || "3000");
  const port = await findAvailablePort(preferredPort);
  const runtimeCallbackUrl = process.env.GOOGLE_CALLBACK_URL ?? `http://localhost:${port}/api/auth/google/callback`;
  console.log(`[Google OAuth] callback URL = ${runtimeCallbackUrl}`);

  // Initialize Passport with the resolved callback URL
  initializePassport(runtimeCallbackUrl);
  
  // Configure session middleware
  app.use(
    session({
      secret: ENV.cookieSecret,
      resave: false,
      saveUninitialized: false,
      cookie: {
        secure: ENV.isProduction,
        httpOnly: true,
        sameSite: "lax",
        maxAge: 1000 * 60 * 60 * 24 * 365, // 1 year
      },
    })
  );
  
  // Initialize Passport middleware
  app.use(passport.initialize());
  app.use(passport.session());
  
  // Configure CORS
  app.use((req, res, next) => {
    const origin = req.headers.origin || "*";
    res.header("Access-Control-Allow-Origin", origin);
    res.header("Access-Control-Allow-Methods", "GET, POST, PUT, DELETE, OPTIONS");
    res.header("Access-Control-Allow-Headers", "Content-Type, Authorization");
    res.header("Access-Control-Allow-Credentials", "true");
    
    if (req.method === "OPTIONS") {
      return res.sendStatus(200);
    }
    next();
  });
  
  // Configure body parser with larger size limit for file uploads
  app.use(express.json({ limit: "50mb" }));
  app.use(express.urlencoded({ limit: "50mb", extended: true }));
  registerStorageProxy(app);
  registerImageRoutes(app);
  registerOAuthRoutes(app);
  registerGoogleAuthRoutes(app);
  registerMobileApiRoutes(app);
  registerGestaoSsoRoutes(app);

  // ── SEO: robots.txt ───────────────────────────────────────────────────────
  app.get("/robots.txt", (req, res) => {
    // Use request host/proto when available so both prof-aid.com and www.prof-aid.com are accepted
    const host = req.get('host') || new URL(ENV.siteBase).host;
    const forwardedProto = (req.headers['x-forwarded-proto'] as string) || '';
    const proto = forwardedProto.split(',')[0] || (ENV.isProduction ? 'https' : req.protocol);
    const base = `${proto}://${host}`;
    res.type("text/plain").send(
      "User-agent: *\n" +
      "Allow: /\n" +
      "Disallow: /api/\n" +
      "Disallow: /admin\n" +
      "\n" +
      `Sitemap: ${base}/sitemap.xml\n`
    );
  });

  // ── SEO: sitemap.xml ──────────────────────────────────────────────────────
  app.get("/sitemap.xml", (req, res) => {
    // Use request host/proto to build links so both domains are valid and indexable
    const host = req.get('host') || new URL(ENV.siteBase).host;
    const forwardedProto = (req.headers['x-forwarded-proto'] as string) || '';
    const proto = forwardedProto.split(',')[0] || (ENV.isProduction ? 'https' : req.protocol);
    const base = `${proto}://${host}`;
    const now  = new Date().toISOString().split("T")[0];
    const urls = [
      { loc: "/",        changefreq: "weekly",  priority: "1.0" },
      { loc: "/login",   changefreq: "monthly", priority: "0.7" },
      { loc: "/signup",  changefreq: "monthly", priority: "0.8" },
      { loc: "/pricing", changefreq: "monthly", priority: "0.7" },
      { loc: "/faq",     changefreq: "monthly", priority: "0.6" },
    ];
    const urlsXml = urls.map(u =>
      `  <url>\n    <loc>${base}${u.loc}</loc>\n    <lastmod>${now}</lastmod>\n    <changefreq>${u.changefreq}</changefreq>\n    <priority>${u.priority}</priority>\n  </url>`
    ).join("\n");
    res.type("application/xml").send(
      `<?xml version="1.0" encoding="UTF-8"?>\n` +
      `<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n` +
      urlsXml + "\n</urlset>"
    );
  });
  // tRPC API
  app.use(
    "/api/trpc",
    createExpressMiddleware({
      router: appRouter,
      createContext,
    })
  );

  // Healthcheck endpoint for uptime/monitoring
  app.get('/healthz', (_req, res) => {
    res.json({ status: 'ok', uptime: process.uptime(), time: new Date().toISOString() });
  });

  // Local QR code generator endpoint (returns SVG)
  app.get('/api/qr', async (req, res) => {
    const data = typeof req.query.data === 'string' ? req.query.data : '';
    const size = parseInt(typeof req.query.size === 'string' ? req.query.size : '120', 10) || 120;
    if (!data) return res.status(400).send('missing data');
    try {
      const svg = await QRCode.toString(data, { type: 'svg', width: size, margin: 0 });
      res.type('image/svg+xml').send(svg);
    } catch (err) {
      res.status(500).send('qr generation failed');
    }
  });

  // Simple telemetry endpoint (accepts JSON events from the client)
  app.post('/api/telemetry', (req, res) => {
    try {
      const payload = req.body;
      // Minimal server-side handling: log to stdout for now
      console.log('[Telemetry]', JSON.stringify(payload));
      res.json({ ok: true });
    } catch (err) {
      console.error('[Telemetry] failed to process event', err);
      res.status(500).json({ ok: false });
    }
  });
  // MercadoPago subscription webhook
  app.post('/api/webhooks/mercadopago', express.json(), async (req, res) => {
    try {
      const { type, data } = req.body ?? {};
      if (type !== "preapproval" || !data?.id) { res.sendStatus(200); return; }

      const token = process.env.MERCADOPAGO_ACCESS_TOKEN;
      if (!token) { res.sendStatus(200); return; }

      const { MercadoPagoConfig, PreApproval } = await import("mercadopago");
      const client = new MercadoPagoConfig({ accessToken: token });
      const mpPreApproval = new PreApproval(client);
      const detail = await mpPreApproval.get({ id: data.id });

      const { getDb, subscriptions } = await import("../db");
      const { applyPlanToUser } = await import("../routers/billing");
      const { eq } = await import("drizzle-orm");

      const db = await getDb();
      if (!db) { res.sendStatus(500); return; }

      const subRows = await db.select().from(subscriptions)
        .where(eq(subscriptions.mpPreapprovalId, data.id))
        .limit(1);

      if (!subRows.length) { res.sendStatus(200); return; }
      const sub = subRows[0] as any;
      const newStatus = detail.status as string;

      // Parse next billing date from MP response
      const nextPaymentRaw = (detail as any).summarized?.next_scheduled_payment_date
        ?? (detail as any).next_payment_date;
      const currentPeriodEnd = nextPaymentRaw ? new Date(nextPaymentRaw) : undefined;

      await db.update(subscriptions)
        .set({
          status: newStatus,
          ...(currentPeriodEnd ? { currentPeriodEnd } : {}),
          updatedAt: new Date(),
        })
        .where(eq(subscriptions.id, sub.id));

      if (newStatus === "authorized") {
        await applyPlanToUser(db, sub.userId, sub.planKey);
      } else if (newStatus === "cancelled" || newStatus === "ended") {
        await applyPlanToUser(db, sub.userId, "free");
      }

      res.sendStatus(200);
    } catch (err) {
      console.error("[MP Webhook]", err);
      res.sendStatus(500);
    }
  });

  // development mode uses Vite, production mode uses static files
  if (process.env.NODE_ENV === "development") {
    await setupVite(app, server);
  } else {
    serveStatic(app);
  }

  if (port !== preferredPort) {
    console.log(`Port ${preferredPort} is busy, using port ${port} instead`);
  }

  server.listen(port, () => {
    console.log(`Server running on http://localhost:${port}/`);
  });
}

startServer().catch(console.error);
