import { Page, Response } from 'playwright';
import * as fs from 'fs';
import * as path from 'path';
import { CheckContext, Evidence, ResultStatus, RuleResult } from './types.js';

export function resolveUrl(base: string, routeOrUrl: string): string {
  try {
    return new URL(routeOrUrl, base).toString();
  } catch {
    return routeOrUrl;
  }
}

export function isSameOrigin(base: string, url: string): boolean {
  try {
    return new URL(base).origin === new URL(url).origin;
  } catch {
    return false;
  }
}

export async function takeScreenshot(page: Page, ctx: CheckContext, ruleCode: string): Promise<string | null> {
  try {
    fs.mkdirSync(ctx.screenshotDir, { recursive: true });
    const filePath = path.join(ctx.screenshotDir, `${ruleCode}.png`);
    await page.screenshot({ path: filePath, fullPage: false, timeout: 5000 });
    return filePath;
  } catch {
    return null;
  }
}

export function baseEvidence(ctx: CheckContext, extra: Partial<Evidence> = {}): Evidence {
  return {
    browser: ctx.browserName,
    timestamp: new Date().toISOString(),
    console_errors: [],
    network_errors: [],
    ...extra,
  };
}

export function makeResult(
  ruleCode: string,
  status: ResultStatus,
  testedPage: string | null,
  expected: string | null,
  observed: string | null,
  evidence: Evidence,
  startedAt: number,
  applicabilityOverride?: 'not_applicable'
): RuleResult {
  return {
    rule_code: ruleCode,
    status,
    tested_page: testedPage,
    expected,
    observed,
    evidence,
    duration_ms: Date.now() - startedAt,
    ...(applicabilityOverride ? { applicability_override: applicabilityOverride } : {}),
  };
}

/** Navigate and swallow navigation errors into a consistent shape instead of throwing. */
export async function safeGoto(
  page: Page,
  url: string,
  timeoutMs: number
): Promise<{ response: Response | null; error: string | null }> {
  try {
    const response = await page.goto(url, { timeout: timeoutMs, waitUntil: 'load' });
    return { response, error: null };
  } catch (e: any) {
    return { response: null, error: e?.message ?? String(e) };
  }
}

export function collectConsoleAndNetworkErrors(page: Page): { consoleErrors: string[]; networkErrors: string[]; pageErrors: string[] } {
  const consoleErrors: string[] = [];
  const networkErrors: string[] = [];
  const pageErrors: string[] = [];

  page.on('console', (msg) => {
    if (msg.type() === 'error') consoleErrors.push(msg.text());
  });
  page.on('pageerror', (err) => {
    pageErrors.push(err.message);
  });
  page.on('requestfailed', (req) => {
    networkErrors.push(`${req.method()} ${req.url()} - ${req.failure()?.errorText ?? 'failed'}`);
  });

  return { consoleErrors, networkErrors, pageErrors };
}

