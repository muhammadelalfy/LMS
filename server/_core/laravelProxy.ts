import type { Express, Request, Response, NextFunction } from "express";

const LARAVEL_API_URL = (process.env.LARAVEL_API_URL || "http://127.0.0.1:8000").replace(/\/$/, "");
const HOP_BY_HOP_HEADERS = new Set(["connection", "content-length", "transfer-encoding", "keep-alive"]);

export function registerLaravelApiProxy(app: Express) {
  app.use("/api", async (req: Request, res: Response, next: NextFunction) => {
    if (req.path.startsWith("/trpc")) return next();

    try {
      const upstreamUrl = `${LARAVEL_API_URL}${req.originalUrl}`;
      const headers = new Headers();
      Object.entries(req.headers).forEach(([key, value]) => {
        if (HOP_BY_HOP_HEADERS.has(key.toLowerCase()) || value === undefined) return;
        headers.set(key, Array.isArray(value) ? value.join(", ") : value);
      });
      headers.set("accept", "application/json");

      const hasBody = !["GET", "HEAD"].includes(req.method);
      const upstream = await fetch(upstreamUrl, {
        method: req.method,
        headers,
        body: hasBody ? JSON.stringify(req.body ?? {}) : undefined,
      });

      res.status(upstream.status);
      upstream.headers.forEach((value, key) => {
        if (!HOP_BY_HOP_HEADERS.has(key.toLowerCase())) res.setHeader(key, value);
      });
      res.send(Buffer.from(await upstream.arrayBuffer()));
    } catch (error) {
      console.error("[Laravel API proxy] Request failed", error);
      res.status(502).json({ message: "Laravel API is unavailable", code: "LARAVEL_API_UNAVAILABLE" });
    }
  });
}
