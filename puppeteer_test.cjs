const puppeteer = require('puppeteer');
const path = require('path');
const fs = require('fs');

(async () => {
  console.log('Launching browser...');
  const browser = await puppeteer.launch({ headless: 'new' });
  const page = await browser.newPage();
  await page.setViewport({ width: 1400, height: 900 });

  page.on('console', msg => console.log('BROWSER LOG:', msg.text()));
  page.on('pageerror', err => console.log('BROWSER ERROR:', err.toString()));

  console.log('Navigating to login...');
  await page.goto('http://localhost/admin/login');
  
  await page.type('input[type="email"]', 'admin@dentalcare.com');
  await page.type('input[type="password"]', 'password');
  await page.click('button[type="submit"]');

  console.log('Waiting for login to complete...');
  await page.waitForNavigation();

  console.log('Navigating to odontogram...');
  await page.goto('http://localhost/patients/1/odontogram?dark=1');

  // Wait 7 seconds for 3D model to load
  console.log('Waiting for 3D model to load...');
  await new Promise(r => setTimeout(r, 7000));

  const outDir = 'C:\\Users\\ATG\\.gemini\\antigravity-ide\\brain\\6924b39c-6c40-47eb-abb1-1fe19e58c5b3\\scratch';
  if (!fs.existsSync(outDir)) {
    fs.mkdirSync(outDir, { recursive: true });
  }

  await page.screenshot({ path: path.join(outDir, 'odontogram_initial.png') });
  console.log('Initial screenshot saved.');

  // Click Upper (Maxilla)
  const buttons = await page.$$('button');
  for (const btn of buttons) {
    const text = await page.evaluate(el => el.textContent, btn);
    if (text && text.includes('Upper (Maxilla)')) {
      console.log('Clicking Upper Arch button...');
      await btn.click();
      break;
    }
  }

  await new Promise(r => setTimeout(r, 2000));
  await page.screenshot({ path: path.join(outDir, 'odontogram_upper.png') });
  console.log('Upper screenshot saved.');

  // Click Lower (Mandible)
  for (const btn of buttons) {
    const text = await page.evaluate(el => el.textContent, btn);
    if (text && text.includes('Lower (Mandible)')) {
      console.log('Clicking Lower Arch button...');
      await btn.click();
      break;
    }
  }

  await new Promise(r => setTimeout(r, 2000));
  await page.screenshot({ path: path.join(outDir, 'odontogram_lower.png') });
  console.log('Lower screenshot saved.');

  await browser.close();
  console.log('Test completed successfully!');
})();
