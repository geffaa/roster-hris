import { execSync } from 'node:child_process';

export default function setup() {
    execSync('php artisan migrate:fresh --seed --force', { env: { ...process.env, DB_DATABASE: 'roster_e2e' }, stdio: 'inherit' });
}
