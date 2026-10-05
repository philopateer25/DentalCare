const puppeteer = require('puppeteer');
const path = require('path');

(async () => {
  console.log('Launching browser...');
  const browser = await puppeteer.launch({ headless: 'new' });
  const page = await browser.newPage();
  await page.setViewport({ width: 1280, height: 800 });

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

  // Wait for 10 seconds for everything to load (including 3D model)
  console.log('Waiting for 3D model to load...');
  await new Promise(r => setTimeout(r, 10000));

  const screenshotPath = path.join('C:\\Users\\ATG\\.gemini\\antigravity-ide\\brain\\c1969f06-0806-4daa-b1c5-cfd1fc34f830', 'scratch', 'screenshot.png');
  await page.screenshot({ path: screenshotPath });
  console.log('Screenshot saved to ' + screenshotPath);

  // Print out the HTML of the app div
  const html = await page.evaluate(() => {
    return document.body.innerHTML;
  });
  const fs = require('fs');
  fs.writeFileSync(path.join('C:\\Users\\ATG\\.gemini\\antigravity-ide\\brain\\c1969f06-0806-4daa-b1c5-cfd1fc34f830', 'scratch', 'body.html'), html);
  console.log('HTML saved.');

  await browser.close();
})();
