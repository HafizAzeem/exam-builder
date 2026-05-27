import puppeteer from 'puppeteer';
import { fileURLToPath } from 'url';

const args = process.argv.slice(2).reduce((acc, arg) => {
    const [key, value] = arg.replace(/^--/, '').split('=');
    acc[key] = value;
    return acc;
}, {});

const url = args.url;
const output = args.output;

if (!url || !output) {
    console.error('Usage: node generate.js --url=<url> --output=<path>');
    process.exit(1);
}

const browser = await puppeteer.launch({ headless: true });
const page = await browser.newPage();
await page.goto(url, { waitUntil: 'networkidle0' });
await page.pdf({ path: output, format: 'A4', printBackground: true });
await browser.close();
