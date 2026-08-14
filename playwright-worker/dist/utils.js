import * as fs from 'fs';
import * as path from 'path';
export function resolveUrl(base, routeOrUrl) {
    try {
        return new URL(routeOrUrl, base).toString();
    }
    catch {
        return routeOrUrl;
    }
}
export function isSameOrigin(base, url) {
    try {
        return new URL(base).origin === new URL(url).origin;
    }
    catch {
        return false;
    }
}
export async function takeScreenshot(page, ctx, ruleCode) {
    try {
        fs.mkdirSync(ctx.screenshotDir, { recursive: true });
        const filePath = path.join(ctx.screenshotDir, `${ruleCode}.png`);
        await page.screenshot({ path: filePath, fullPage: false, timeout: 5000 });
        return filePath;
    }
    catch {
        return null;
    }
}
export function baseEvidence(ctx, extra = {}) {
    return {
        browser: ctx.browserName,
        timestamp: new Date().toISOString(),
        console_errors: [],
        network_errors: [],
        ...extra,
    };
}
export function makeResult(ruleCode, status, testedPage, expected, observed, evidence, startedAt, applicabilityOverride) {
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
export async function safeGoto(page, url, timeoutMs) {
    try {
        const response = await page.goto(url, { timeout: timeoutMs, waitUntil: 'load' });
        return { response, error: null };
    }
    catch (e) {
        return { response: null, error: e?.message ?? String(e) };
    }
}
export function collectConsoleAndNetworkErrors(page) {
    const consoleErrors = [];
    const networkErrors = [];
    const pageErrors = [];
    page.on('console', (msg) => {
        if (msg.type() === 'error')
            consoleErrors.push(msg.text());
    });
    page.on('pageerror', (err) => {
        pageErrors.push(err.message);
    });
    page.on('requestfailed', (req) => {
        networkErrors.push(`${req.method()} ${req.url()} - ${req.failure()?.errorText ?? 'failed'}`);
    });
    return { consoleErrors, networkErrors, pageErrors };
}
