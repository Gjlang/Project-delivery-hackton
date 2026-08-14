// Controlled local fixture site for Playwright integration verification only.
// Strictly dev/test infrastructure -- intentionally contains bugs so the
// SC/TS handlers have real, known PASS/FAIL cases to verify against.
// NOT part of the production application.
import http from 'node:http';
import { URL } from 'node:url';

const PORT = process.env.FIXTURE_PORT ? Number(process.env.FIXTURE_PORT) : 4173;
const sessions = new Map(); // cookie value -> { role }

function parseCookies(req) {
  const header = req.headers.cookie ?? '';
  return Object.fromEntries(
    header.split(';').filter(Boolean).map((p) => {
      const [k, ...v] = p.trim().split('=');
      return [k, decodeURIComponent(v.join('='))];
    })
  );
}

function page(title, body) {
  return `<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>${title}</title></head>
<body>
${body}
<script>
  // Intentional bug: fixture-wide uncaught JS error for TS-018/TS-019 verification.
  window.addEventListener('load', function () {
    undefinedFixtureFunction();
  });
</script>
</body>
</html>`;
}

const server = http.createServer((req, res) => {
  const url = new URL(req.url, `http://${req.headers.host}`);
  const cookies = parseCookies(req);
  const session = sessions.get(cookies.session);

  if (req.method === 'GET' && url.pathname === '/') {
    res.writeHead(200, { 'Content-Type': 'text/html' });
    res.end(page('Fixture Home', `
      <h1>Fixture Home</h1>
      <nav>
        <a href="/">Home</a>
        <a href="/dashboard">Dashboard</a>
        <a href="/admin">Admin</a>
        <a href="/login">Login</a>
        <a href="/contact">Contact</a>
        <a href="/nope-this-does-not-exist">Broken Link</a>
      </nav>
      <!-- Intentional bug: icon-only button, no accessible name -- TS-024 -->
      <button onclick="void(0)">&#9881;</button>
      <button aria-label="Refresh">&#8635;</button>
    `));
    return;
  }

  if (req.method === 'GET' && url.pathname === '/dashboard') {
    // Intentional bug: no session check at all -- SC-003 should FAIL here.
    res.writeHead(200, { 'Content-Type': 'text/html' });
    res.end(page('Dashboard', `<h1>Dashboard</h1><p>Secret dashboard content, visible to anyone.</p><a href="/logout">Logout</a>`));
    return;
  }

  if (req.method === 'GET' && url.pathname === '/admin') {
    if (!session || session.role !== 'admin') {
      res.writeHead(302, { Location: '/login' });
      res.end();
      return;
    }
    res.writeHead(200, { 'Content-Type': 'text/html' });
    res.end(page('Admin', `<h1>Admin Panel</h1><p>Only visible when logged in as admin.</p><a href="/logout">Logout</a>`));
    return;
  }

  if (req.method === 'GET' && url.pathname === '/login') {
    res.writeHead(200, { 'Content-Type': 'text/html' });
    res.end(page('Login', `
      <h1>Login</h1>
      <form method="POST" action="/login">
        <label for="username">Username</label>
        <input id="username" name="username" type="text" />
        <label for="password">Password</label>
        <input id="password" name="password" type="password" />
        <button type="submit">Log in</button>
      </form>
    `));
    return;
  }

  if (req.method === 'POST' && url.pathname === '/login') {
    let body = '';
    req.on('data', (c) => (body += c));
    req.on('end', () => {
      const params = new URLSearchParams(body);
      const username = params.get('username');
      const password = params.get('password');

      if (username === 'admin' && password === 'admin123') {
        const token = 'sess_' + Math.random().toString(36).slice(2);
        sessions.set(token, { role: 'admin' });
        res.writeHead(302, { Location: '/admin', 'Set-Cookie': `session=${token}; Path=/; HttpOnly` });
        res.end();
        return;
      }

      res.writeHead(200, { 'Content-Type': 'text/html' });
      res.end(page('Login', `
        <h1>Login</h1>
        <div role="alert" class="error">Invalid username or password.</div>
        <form method="POST" action="/login">
          <label for="username">Username</label>
          <input id="username" name="username" type="text" />
          <label for="password">Password</label>
          <input id="password" name="password" type="password" />
          <button type="submit">Log in</button>
        </form>
      `));
    });
    return;
  }

  if (req.method === 'GET' && url.pathname === '/logout') {
    sessions.delete(cookies.session);
    res.writeHead(302, { Location: '/', 'Set-Cookie': 'session=; Path=/; Max-Age=0' });
    res.end();
    return;
  }

  if (req.method === 'GET' && url.pathname === '/contact') {
    res.writeHead(200, { 'Content-Type': 'text/html' });
    res.end(page('Contact', `
      <h1>Contact</h1>
      <form method="POST" action="/contact">
        <!-- Intentional bug: no label / aria-label on this input -- TS-009 -->
        <input id="email" name="email" type="email" required />
        <button type="submit">Send</button>
      </form>
    `));
    return;
  }

  if (req.method === 'POST' && url.pathname === '/contact') {
    res.writeHead(200, { 'Content-Type': 'text/html' });
    res.end(page('Contact', `<h1>Thanks!</h1><div class="success">Message sent.</div>`));
    return;
  }

  res.writeHead(404, { 'Content-Type': 'text/html' });
  res.end(page('Not Found', '<h1>404 Not Found</h1>'));
});

server.listen(PORT, () => {
  console.log(`Fixture site running at http://localhost:${PORT}`);
});
