import { baseEvidence, makeResult, resolveUrl, safeGoto, takeScreenshot } from '../utils.js';
export async function checkHttps(browser, ctx) {
    const start = Date.now();
    const isHttps = ctx.websiteUrl.startsWith('https://');
    const evidence = baseEvidence(ctx, { url: ctx.websiteUrl });
    return makeResult('SC-001', isHttps ? 'PASS' : 'FAIL', ctx.websiteUrl, 'The website must use HTTPS for sensitive workflows.', isHttps ? 'The site is served over HTTPS.' : `The site is served over an insecure scheme (${new URL(ctx.websiteUrl).protocol}).`, evidence, start);
}
export async function checkMixedContent(browser, ctx) {
    const start = Date.now();
    if (!ctx.websiteUrl.startsWith('https://')) {
        return makeResult('SC-002', 'NOT_TESTABLE', ctx.websiteUrl, null, 'Site is not served over HTTPS, so mixed-content is not applicable.', baseEvidence(ctx), start, 'not_applicable');
    }
    const context = await browser.newContext();
    const page = await context.newPage();
    const insecureRequests = [];
    page.on('request', (req) => {
        if (req.url().startsWith('http://'))
            insecureRequests.push(req.url());
    });
    const { response, error } = await safeGoto(page, ctx.websiteUrl, ctx.timeoutMs);
    const evidence = baseEvidence(ctx, { url: ctx.websiteUrl, http_status: response?.status() ?? null, network_errors: insecureRequests });
    await context.close();
    if (error) {
        return makeResult('SC-002', 'NOT_TESTABLE', ctx.websiteUrl, null, `Could not load page: ${error}`, evidence, start);
    }
    const status = insecureRequests.length > 0 ? 'FAIL' : 'PASS';
    return makeResult('SC-002', status, ctx.websiteUrl, 'HTTPS pages must not depend on insecure HTTP resources.', status === 'FAIL' ? `Loaded ${insecureRequests.length} resource(s) over insecure HTTP.` : 'No insecure HTTP resources were loaded.', evidence, start);
}
async function attemptRoutes(browser, ctx, routes) {
    const context = await browser.newContext();
    const page = await context.newPage();
    const attempts = [];
    for (const route of routes) {
        const url = resolveUrl(ctx.websiteUrl, route);
        const { response } = await safeGoto(page, url, ctx.timeoutMs);
        const finalUrl = page.url();
        attempts.push({
            route,
            url,
            status: response?.status() ?? null,
            finalUrl,
            redirected: finalUrl !== url,
        });
    }
    return { context, page, attempts };
}
export async function checkProtectedPage(browser, ctx) {
    const start = Date.now();
    const routes = ctx.testContext.protected_routes ?? [];
    const { context, page, attempts } = await attemptRoutes(browser, ctx, routes);
    const stillAccessible = attempts.filter((a) => !a.redirected && (a.status ?? 0) >= 200 && (a.status ?? 0) < 300);
    const screenshot = stillAccessible.length > 0 ? await takeScreenshot(page, ctx, 'SC-003') : null;
    await context.close();
    const evidence = baseEvidence(ctx, { screenshot, url: attempts[0]?.url });
    const status = stillAccessible.length > 0 ? 'FAIL' : 'PASS';
    return makeResult('SC-003', status, routes.join(', '), 'Protected pages must not be accessible using an unauthenticated browser session.', status === 'FAIL'
        ? `${stillAccessible.length} protected route(s) loaded without authentication: ${stillAccessible.map((a) => a.route).join(', ')}.`
        : 'All protected routes redirected/blocked an unauthenticated session.', evidence, start);
}
export async function checkUnauthorizedBehaviour(browser, ctx) {
    const start = Date.now();
    const routes = ctx.testContext.protected_routes ?? [];
    const { context, attempts } = await attemptRoutes(browser, ctx, routes);
    await context.close();
    const blocked = attempts.filter((a) => a.redirected || (a.status ?? 0) === 401 || (a.status ?? 0) === 403);
    const evidence = baseEvidence(ctx);
    const status = blocked.length === attempts.length && attempts.length > 0 ? 'PASS' : attempts.length === 0 ? 'NOT_TESTABLE' : 'WARNING';
    return makeResult('SC-004', status, routes.join(', '), 'Unauthorized users should be redirected, denied access, or otherwise blocked.', `${blocked.length}/${attempts.length} route(s) showed redirect/401/403 behaviour for an unauthenticated request.`, evidence, start);
}
export async function checkLogoutProtection(browser, ctx) {
    const start = Date.now();
    const { login_url, valid_credentials, protected_routes } = ctx.testContext;
    const context = await browser.newContext();
    const page = await context.newPage();
    await safeGoto(page, resolveUrl(ctx.websiteUrl, login_url), ctx.timeoutMs);
    const userSel = ctx.testContext.username_field_selector ?? 'input[type="email"], input[name*="email" i], input[name*="user" i]';
    const passSel = ctx.testContext.password_field_selector ?? 'input[type="password"]';
    const submitSel = ctx.testContext.login_submit_selector ?? 'button[type="submit"], input[type="submit"]';
    try {
        await page.fill(userSel, valid_credentials.username, { timeout: 5000 });
        await page.fill(passSel, valid_credentials.password, { timeout: 5000 });
        await page.click(submitSel, { timeout: 5000 });
        await page.waitForLoadState('load', { timeout: ctx.timeoutMs }).catch(() => { });
    }
    catch (e) {
        await context.close();
        return makeResult('SC-005', 'NOT_TESTABLE', login_url ?? null, null, `Could not complete login form: ${e?.message}`, baseEvidence(ctx), start);
    }
    // Best-effort logout: look for a common logout control.
    const logoutSel = 'a:has-text("Log out"), a:has-text("Logout"), button:has-text("Log out"), button:has-text("Logout")';
    const hasLogout = await page.locator(logoutSel).first().isVisible().catch(() => false);
    if (hasLogout) {
        await page.locator(logoutSel).first().click({ timeout: 5000 }).catch(() => { });
        await page.waitForLoadState('load', { timeout: ctx.timeoutMs }).catch(() => { });
    }
    const route = (protected_routes ?? [])[0];
    const { response } = await safeGoto(page, resolveUrl(ctx.websiteUrl, route), ctx.timeoutMs);
    const finalUrl = page.url();
    const stillAccessible = response && response.status() >= 200 && response.status() < 300 && finalUrl === resolveUrl(ctx.websiteUrl, route);
    const screenshot = stillAccessible ? await takeScreenshot(page, ctx, 'SC-005') : null;
    await context.close();
    const evidence = baseEvidence(ctx, { screenshot, url: route });
    const status = !hasLogout ? 'NOT_TESTABLE' : stillAccessible ? 'FAIL' : 'PASS';
    return makeResult('SC-005', status, route ?? null, 'After logout, the same browser session must not retain protected-page access.', !hasLogout ? 'No logout control could be found on the page.' : stillAccessible ? 'Protected page remained accessible after logout.' : 'Protected page was no longer accessible after logout.', evidence, start);
}
export async function checkUrlSensitiveInfo(browser, ctx) {
    const start = Date.now();
    const context = await browser.newContext();
    const page = await context.newPage();
    const visitedUrls = [];
    page.on('framenavigated', (frame) => {
        if (frame === page.mainFrame())
            visitedUrls.push(frame.url());
    });
    await safeGoto(page, ctx.websiteUrl, ctx.timeoutMs);
    await context.close();
    const pattern = /(password|token|secret|session[_-]?id|api[_-]?key)=[^&]+/i;
    const flagged = visitedUrls.filter((u) => pattern.test(u));
    const evidence = baseEvidence(ctx, { url: ctx.websiteUrl });
    return makeResult('SC-006', flagged.length > 0 ? 'FAIL' : 'PASS', ctx.websiteUrl, 'URLs must not expose passwords, tokens, session IDs, or credentials.', flagged.length > 0 ? `${flagged.length} visited URL(s) contained a suspicious query parameter.` : 'No visited URL exposed an obvious credential-like query parameter.', evidence, start);
}
export async function checkPasswordFieldType(browser, ctx) {
    const start = Date.now();
    const context = await browser.newContext();
    const page = await context.newPage();
    const target = ctx.testContext.login_url ? resolveUrl(ctx.websiteUrl, ctx.testContext.login_url) : ctx.websiteUrl;
    await safeGoto(page, target, ctx.timeoutMs);
    const passwordInputs = await page.locator('input[type="password"]').count();
    const textInputsNamedPassword = await page.locator('input[name*="password" i]:not([type="password"])').count();
    await context.close();
    const evidence = baseEvidence(ctx, { url: target });
    if (passwordInputs === 0 && textInputsNamedPassword === 0) {
        return makeResult('SC-007', 'NOT_TESTABLE', target, null, 'No password field was found on the tested page.', evidence, start, 'not_applicable');
    }
    const status = textInputsNamedPassword > 0 ? 'FAIL' : 'PASS';
    return makeResult('SC-007', status, target, 'Password values must be entered through an appropriate password field.', status === 'FAIL' ? 'A password-like field was found that is not using type="password".' : `${passwordInputs} password field(s) correctly use type="password".`, evidence, start);
}
export async function checkVisibleSecrets(browser, ctx) {
    const start = Date.now();
    const context = await browser.newContext();
    const page = await context.newPage();
    await safeGoto(page, ctx.websiteUrl, ctx.timeoutMs);
    const bodyText = await page.locator('body').innerText().catch(() => '');
    await context.close();
    const pattern = /(api[_-]?key|secret[_-]?key|private[_-]?key|password\s*[:=]\s*\S+)/i;
    const match = pattern.test(bodyText);
    const evidence = baseEvidence(ctx, { url: ctx.websiteUrl });
    return makeResult('SC-008', match ? 'FAIL' : 'PASS', ctx.websiteUrl, 'Public pages must not visibly expose obvious passwords, API secrets, tokens, or credentials.', match ? 'Visible page text matched a pattern commonly associated with an exposed secret.' : 'No obviously exposed secret pattern was found in the visible page text (not a comprehensive secret scan).', evidence, start);
}
export async function checkAdminPageProtection(browser, ctx) {
    const start = Date.now();
    const routes = ctx.testContext.admin_routes ?? [];
    const { context, page, attempts } = await attemptRoutes(browser, ctx, routes);
    const stillAccessible = attempts.filter((a) => !a.redirected && (a.status ?? 0) >= 200 && (a.status ?? 0) < 300);
    const screenshot = stillAccessible.length > 0 ? await takeScreenshot(page, ctx, 'SC-009') : null;
    await context.close();
    const evidence = baseEvidence(ctx, { screenshot });
    const status = stillAccessible.length > 0 ? 'FAIL' : 'PASS';
    return makeResult('SC-009', status, routes.join(', '), 'Administrative pages must not be accessible to unauthorized or unauthenticated users.', status === 'FAIL' ? `${stillAccessible.length} admin route(s) were accessible without authentication: ${stillAccessible.map((a) => a.route).join(', ')}.` : 'All admin routes correctly blocked an unauthenticated session.', evidence, start);
}
export async function checkLoginWorkflow(browser, ctx) {
    const start = Date.now();
    const { login_url, valid_credentials, invalid_credentials } = ctx.testContext;
    const context = await browser.newContext();
    const page = await context.newPage();
    const target = resolveUrl(ctx.websiteUrl, login_url);
    const userSel = ctx.testContext.username_field_selector ?? 'input[type="email"], input[name*="email" i], input[name*="user" i]';
    const passSel = ctx.testContext.password_field_selector ?? 'input[type="password"]';
    const submitSel = ctx.testContext.login_submit_selector ?? 'button[type="submit"], input[type="submit"]';
    const steps = [];
    let ok = true;
    try {
        await safeGoto(page, target, ctx.timeoutMs);
        if (invalid_credentials) {
            await page.fill(userSel, invalid_credentials.username, { timeout: 5000 });
            await page.fill(passSel, invalid_credentials.password, { timeout: 5000 });
            await page.click(submitSel, { timeout: 5000 });
            await page.waitForLoadState('load', { timeout: ctx.timeoutMs }).catch(() => { });
            const stillOnLogin = page.url().includes(new URL(target).pathname) || (await page.locator(passSel).count()) > 0;
            steps.push(`invalid login ${stillOnLogin ? 'correctly rejected' : 'was NOT rejected'}`);
            if (!stillOnLogin)
                ok = false;
            await safeGoto(page, target, ctx.timeoutMs);
        }
        await page.fill(userSel, valid_credentials.username, { timeout: 5000 });
        await page.fill(passSel, valid_credentials.password, { timeout: 5000 });
        await page.click(submitSel, { timeout: 5000 });
        await page.waitForLoadState('load', { timeout: ctx.timeoutMs }).catch(() => { });
        const stillHasPasswordField = (await page.locator(passSel).count()) > 0 && page.url() === target;
        steps.push(`valid login ${!stillHasPasswordField ? 'succeeded' : 'did NOT appear to succeed'}`);
        if (stillHasPasswordField)
            ok = false;
    }
    catch (e) {
        await context.close();
        return makeResult('SC-010', 'NOT_TESTABLE', target, null, `Login workflow could not be executed: ${e?.message}`, baseEvidence(ctx), start);
    }
    await context.close();
    const evidence = baseEvidence(ctx, { url: target });
    return makeResult('SC-010', ok ? 'PASS' : 'FAIL', target, 'Valid login should succeed; invalid login should be rejected.', steps.join('; '), evidence, start);
}
export async function checkRoleBasedAccess(browser, ctx) {
    const start = Date.now();
    const roles = ctx.testContext.role_accounts ?? [];
    const routes = ctx.testContext.protected_routes ?? ctx.testContext.admin_routes ?? [];
    if (roles.length < 2 || routes.length === 0) {
        return makeResult('SC-011', 'NOT_TESTABLE', null, null, 'Fewer than 2 role accounts or no target route was supplied.', baseEvidence(ctx), start);
    }
    const loginUrl = ctx.testContext.login_url;
    if (!loginUrl) {
        return makeResult('SC-011', 'NOT_TESTABLE', null, null, 'No login URL supplied to authenticate role accounts.', baseEvidence(ctx), start);
    }
    const userSel = ctx.testContext.username_field_selector ?? 'input[type="email"], input[name*="email" i], input[name*="user" i]';
    const passSel = ctx.testContext.password_field_selector ?? 'input[type="password"]';
    const submitSel = ctx.testContext.login_submit_selector ?? 'button[type="submit"], input[type="submit"]';
    const observations = [];
    for (const role of roles) {
        const context = await browser.newContext();
        const page = await context.newPage();
        try {
            await safeGoto(page, resolveUrl(ctx.websiteUrl, loginUrl), ctx.timeoutMs);
            await page.fill(userSel, role.username, { timeout: 5000 });
            await page.fill(passSel, role.password, { timeout: 5000 });
            await page.click(submitSel, { timeout: 5000 });
            await page.waitForLoadState('load', { timeout: ctx.timeoutMs }).catch(() => { });
            const { response } = await safeGoto(page, resolveUrl(ctx.websiteUrl, routes[0]), ctx.timeoutMs);
            const accessible = (response?.status() ?? 0) >= 200 && (response?.status() ?? 0) < 300 && page.url() === resolveUrl(ctx.websiteUrl, routes[0]);
            observations.push(`${role.role}: ${accessible ? 'accessible' : 'blocked'}`);
        }
        catch (e) {
            observations.push(`${role.role}: error (${e?.message})`);
        }
        finally {
            await context.close();
        }
    }
    const evidence = baseEvidence(ctx, { url: routes[0] });
    return makeResult('SC-011', 'WARNING', routes[0], 'Authorized and unauthorized roles should be tested against the route.', observations.join('; ') + ' -- review manually whether this access pattern is intended.', evidence, start);
}
export async function checkErrorInfoExposure(browser, ctx) {
    const start = Date.now();
    const context = await browser.newContext();
    const page = await context.newPage();
    const target = resolveUrl(ctx.websiteUrl, '/this-path-should-not-exist-' + Date.now());
    const { response } = await safeGoto(page, target, ctx.timeoutMs);
    const bodyText = await page.locator('body').innerText().catch(() => '');
    await context.close();
    const pattern = /(stack trace|at\s+\S+\.(js|php|ts):\d+|SQLSTATE|mysqli_|ORA-\d+|Exception in thread)/i;
    const exposed = pattern.test(bodyText);
    const evidence = baseEvidence(ctx, { url: target, http_status: response?.status() ?? null });
    return makeResult('SC-012', exposed ? 'FAIL' : 'PASS', target, 'Error pages must not expose stack traces, database details, or internal infrastructure information.', exposed ? 'The error page contained content resembling a stack trace or backend error detail.' : 'No obvious backend error detail was found on the error page.', evidence, start);
}
