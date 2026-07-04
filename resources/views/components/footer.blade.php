<footer class="border-t border-slate-200/80 bg-white dark:border-slate-800/80 dark:bg-slate-950/80 py-16 transition-colors duration-300">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-10">
            
            <!-- Company / Brand Identity Column -->
            <div class="lg:col-span-2">
                <a href="/" class="flex items-center gap-x-2">
                    <span class="h-8 w-8 rounded-lg bg-indigo-600 flex items-center justify-center text-white font-extrabold text-sm shadow-sm">J</span>
                    <span class="text-sm font-black tracking-widest text-slate-900 dark:text-white uppercase font-display">JUANET</span>
                </a>
                <p class="mt-4 text-xs text-slate-500 dark:text-slate-400 leading-relaxed max-w-sm">
                    Premium enterprise project management and digital CRM workflows integrated with high-performance billing pipelines. Trusted by scaling software agencies.
                </p>
                
                <!-- Social links -->
                <div class="mt-6 flex items-center gap-x-4">
                    <!-- X (Twitter) -->
                    <a href="#" class="text-slate-400 hover:text-slate-600 dark:hover:text-white transition">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                    </a>
                    <!-- GitHub -->
                    <a href="#" class="text-slate-400 hover:text-slate-600 dark:hover:text-white transition">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M12 2C6.477 2 2 6.477 2 12c0 4.42 2.865 8.17 6.839 9.49.5.092.682-.217.682-.482 0-.237-.008-.866-.013-1.7-2.782.603-3.369-1.34-3.369-1.34-.454-1.156-1.11-1.464-1.11-1.464-.908-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.087 2.91.831.092-.646.35-1.086.636-1.336-2.22-.253-4.555-1.11-4.555-4.943 0-1.091.39-1.984 1.029-2.683-.103-.253-.446-1.27.098-2.647 0 0 .84-.269 2.75 1.025A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.294 2.747-1.025 2.747-1.025.546 1.377.203 2.394.1 2.647.64.699 1.028 1.592 1.028 2.683 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.852 0 1.336-.012 2.415-.012 2.743 0 .267.18.578.688.48C19.137 20.167 22 16.418 22 12c0-5.523-4.477-10-10-10z" clip-rule="evenodd"/></svg>
                    </a>
                </div>
            </div>

            <!-- Quick Links -->
            <div>
                <h3 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">Product</h3>
                <ul class="mt-4 space-y-2 text-xs text-slate-500 dark:text-slate-400">
                    <li><a href="#" @click.prevent="alert('Marketplace features')" class="hover:text-indigo-600 transition">Marketplace</a></li>
                    <li><a href="#" @click.prevent="alert('CRM Pipelines')" class="hover:text-indigo-600 transition">CRM Workflows</a></li>
                    <li><a href="#" @click.prevent="alert('Finance ledger module')" class="hover:text-indigo-600 transition">Financial Ledger</a></li>
                    <li><a href="#" @click.prevent="alert('Pricing details')" class="hover:text-indigo-600 transition">Pricing Plans</a></li>
                </ul>
            </div>

            <!-- Company -->
            <div>
                <h3 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">Company</h3>
                <ul class="mt-4 space-y-2 text-xs text-slate-500 dark:text-slate-400">
                    <li><a href="#" class="hover:text-indigo-600 transition">About Us</a></li>
                    <li><a href="#" class="hover:text-indigo-600 transition">Official Blog</a></li>
                    <li><a href="#" class="hover:text-indigo-600 transition">Workplace Careers</a></li>
                    <li><a href="#" class="hover:text-indigo-600 transition">Support Desk</a></li>
                </ul>
            </div>

            <!-- Legal / Compliance -->
            <div>
                <h3 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">Legal</h3>
                <ul class="mt-4 space-y-2 text-xs text-slate-500 dark:text-slate-400">
                    <li><a href="#" class="hover:text-indigo-600 transition">Privacy Policy</a></li>
                    <li><a href="#" class="hover:text-indigo-600 transition">Terms of Service</a></li>
                    <li><a href="#" class="hover:text-indigo-600 transition">Cookie Settings</a></li>
                    <li><a href="#" class="hover:text-indigo-600 transition">SLA Guarantee</a></li>
                </ul>
            </div>

        </div>

        <div class="mt-12 pt-8 border-t border-slate-100 dark:border-slate-800/60 flex flex-col sm:flex-row items-center justify-between gap-y-4">
            <span class="text-[10px] text-slate-400 dark:text-slate-500">
                &copy; 2026 JUANET Enterprise Platform. All rights reserved. Built using Laravel 12 &amp; Tailwind CSS.
            </span>
            <div class="flex items-center gap-x-2 text-[10px] text-slate-400">
                <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Operational Ingress: Nairobi East</span>
            </div>
        </div>
    </div>
</footer>
