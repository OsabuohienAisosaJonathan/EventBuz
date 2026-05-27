<?php
/**
 * EventSnap Cloud - Subscription and Pricing Plans
 * Overhauled to match the premium light-theme design system.
 */
$pageTitle = "Select Subscription Tier";
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 md:py-12">
    <!-- Title Block -->
    <div class="text-center mb-12">
        <span class="inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-bold bg-primary/10 text-primary border border-primary/20 mb-4 uppercase tracking-wider">
            Subscription Plans
        </span>
        <h2 class="text-headline-lg font-bold text-on-surface tracking-tight">Transparent, Value-Focused Pricing</h2>
        <p class="text-on-surface-variant max-w-xl mx-auto mt-2 text-sm">Choose the tier that aligns perfectly with your wedding, corporate meeting, or event scale.</p>
    </div>
    
    <!-- Pricing Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 items-stretch max-w-6xl mx-auto mt-6">
        <!-- 1. Free Trial Plan -->
        <div class="bg-white rounded-xl border border-outline-variant/35 p-8 flex flex-col justify-between shadow-sm hover:shadow-premium hover:-translate-y-1 transition-all duration-300">
            <div>
                <div class="mb-6">
                    <h3 class="text-headline-md font-bold text-on-surface mb-1">Free Sandbox</h3>
                    <p class="text-on-surface-variant/80 text-xs">Ideal for minor test events & personal trials.</p>
                    <div class="flex items-baseline gap-1.5 mt-4">
                        <span class="text-4xl font-extrabold text-on-surface">$0</span>
                        <span class="text-on-surface-variant/60 text-xs">/ event</span>
                    </div>
                </div>
                
                <hr class="border-outline-variant/20 my-6">
                
                <ul class="space-y-4 mb-8">
                    <li class="flex items-start gap-2.5 text-on-surface-variant/90 text-xs">
                        <span class="material-symbols-outlined text-[18px] text-primary flex-shrink-0">check_circle</span>
                        <span>1 Active Event Registry</span>
                    </li>
                    <li class="flex items-start gap-2.5 text-on-surface-variant/90 text-xs">
                        <span class="material-symbols-outlined text-[18px] text-primary flex-shrink-0">check_circle</span>
                        <span>Up to 100 Guest Uploads</span>
                    </li>
                    <li class="flex items-start gap-2.5 text-on-surface-variant/90 text-xs">
                        <span class="material-symbols-outlined text-[18px] text-primary flex-shrink-0">check_circle</span>
                        <span>Digital PNG QR Code Access</span>
                    </li>
                    <li class="flex items-start gap-2.5 text-on-surface-variant/90 text-xs">
                        <span class="material-symbols-outlined text-[18px] text-primary flex-shrink-0">check_circle</span>
                        <span>Standard Cloud Gallery</span>
                    </li>
                    <li class="flex items-start gap-2.5 text-on-surface-variant/40 text-xs">
                        <span class="material-symbols-outlined text-[18px] text-error flex-shrink-0">cancel</span>
                        <span class="line-through">Custom Text Watermarking</span>
                    </li>
                    <li class="flex items-start gap-2.5 text-on-surface-variant/40 text-xs">
                        <span class="material-symbols-outlined text-[18px] text-error flex-shrink-0">cancel</span>
                        <span class="line-through">Live Slideshow Monitor View</span>
                    </li>
                </ul>
            </div>
            
            <a href="register.php" class="w-full text-center py-3.5 px-6 border border-primary text-primary font-bold text-xs rounded-xl hover:bg-primary/5 active:scale-95 transition-all no-underline block mt-auto">
                Activate Free Trial
            </a>
        </div>
        
        <!-- 2. Pro Plan -->
        <div class="bg-white rounded-xl border-2 border-primary p-8 flex flex-col justify-between shadow-lg shadow-primary/5 hover:shadow-premium relative hover:-translate-y-1 transition-all duration-300">
            <span class="absolute -top-3.5 left-1/2 -translate-x-1/2 px-4 py-1 bg-primary text-white text-[10px] font-bold uppercase tracking-wider rounded-full shadow-md">
                Most Popular
            </span>
            
            <div>
                <div class="mb-6">
                    <h3 class="text-headline-md font-bold text-primary mb-1">Pro Event Pass</h3>
                    <p class="text-on-surface-variant/80 text-xs">Designed for weddings, birthdays, & festivals.</p>
                    <div class="flex items-baseline gap-1.5 mt-4">
                        <span class="text-4xl font-extrabold text-on-surface">$19</span>
                        <span class="text-on-surface-variant/60 text-xs">/ event</span>
                    </div>
                </div>
                
                <hr class="border-outline-variant/20 my-6">
                
                <ul class="space-y-4 mb-8">
                    <li class="flex items-start gap-2.5 text-on-surface-variant/90 text-xs">
                        <span class="material-symbols-outlined text-[18px] text-primary flex-shrink-0" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                        <span class="font-semibold text-on-surface">1 Active Event Registry</span>
                    </li>
                    <li class="flex items-start gap-2.5 text-on-surface-variant/90 text-xs">
                        <span class="material-symbols-outlined text-[18px] text-primary flex-shrink-0" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                        <span class="font-semibold text-on-surface">Unlimited Guest Uploads</span>
                    </li>
                    <li class="flex items-start gap-2.5 text-on-surface-variant/90 text-xs">
                        <span class="material-symbols-outlined text-[18px] text-primary flex-shrink-0" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                        <span class="font-semibold text-on-surface">High-Definition QR Code (PDF)</span>
                    </li>
                    <li class="flex items-start gap-2.5 text-on-surface-variant/90 text-xs">
                        <span class="material-symbols-outlined text-[18px] text-primary flex-shrink-0" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                        <span class="font-semibold text-on-surface">Live Slideshow Monitor View</span>
                    </li>
                    <li class="flex items-start gap-2.5 text-on-surface-variant/90 text-xs">
                        <span class="material-symbols-outlined text-[18px] text-primary flex-shrink-0" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                        <span class="font-semibold text-on-surface">Branded Image Watermarks</span>
                    </li>
                    <li class="flex items-start gap-2.5 text-on-surface-variant/90 text-xs">
                        <span class="material-symbols-outlined text-[18px] text-primary flex-shrink-0" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                        <span class="font-semibold text-on-surface">ZIP Archive Export Support</span>
                    </li>
                </ul>
            </div>
            
            <a href="register.php" class="w-full text-center py-3.5 px-6 bg-primary text-white font-bold text-xs rounded-xl hover:bg-primary-container hover:shadow-lg active:scale-95 transition-all no-underline block mt-auto">
                Purchase Pro Pass
            </a>
        </div>
        
        <!-- 3. Premium Planner Plan -->
        <div class="bg-white rounded-xl border border-outline-variant/35 p-8 flex flex-col justify-between shadow-sm hover:shadow-premium hover:-translate-y-1 transition-all duration-300">
            <div>
                <div class="mb-6">
                    <h3 class="text-headline-md font-bold text-on-surface mb-1">Corporate Planner</h3>
                    <p class="text-on-surface-variant/80 text-xs">For professional wedding planners & event agencies.</p>
                    <div class="flex items-baseline gap-1.5 mt-4">
                        <span class="text-4xl font-extrabold text-on-surface">$49</span>
                        <span class="text-on-surface-variant/60 text-xs">/ month</span>
                    </div>
                </div>
                
                <hr class="border-outline-variant/20 my-6">
                
                <ul class="space-y-4 mb-8">
                    <li class="flex items-start gap-2.5 text-on-surface-variant/90 text-xs">
                        <span class="material-symbols-outlined text-[18px] text-primary flex-shrink-0">check_circle</span>
                        <span>Unlimited Active Events</span>
                    </li>
                    <li class="flex items-start gap-2.5 text-on-surface-variant/90 text-xs">
                        <span class="material-symbols-outlined text-[18px] text-primary flex-shrink-0">check_circle</span>
                        <span>Unlimited High-Definition Uploads</span>
                    </li>
                    <li class="flex items-start gap-2.5 text-on-surface-variant/90 text-xs">
                        <span class="material-symbols-outlined text-[18px] text-primary flex-shrink-0">check_circle</span>
                        <span>Dedicated Media Crew Portals</span>
                    </li>
                    <li class="flex items-start gap-2.5 text-on-surface-variant/90 text-xs">
                        <span class="material-symbols-outlined text-[18px] text-primary flex-shrink-0">check_circle</span>
                        <span>Custom Event Subdomain Toggles</span>
                    </li>
                    <li class="flex items-start gap-2.5 text-on-surface-variant/90 text-xs">
                        <span class="material-symbols-outlined text-[18px] text-primary flex-shrink-0">check_circle</span>
                        <span>Advanced Analytics & Moderator Controls</span>
                    </li>
                    <li class="flex items-start gap-2.5 text-on-surface-variant/90 text-xs">
                        <span class="material-symbols-outlined text-[18px] text-primary flex-shrink-0">check_circle</span>
                        <span>VIP Dedicated Priority Support</span>
                    </li>
                </ul>
            </div>
            
            <a href="register.php" class="w-full text-center py-3.5 px-6 border border-primary text-primary font-bold text-xs rounded-xl hover:bg-primary/5 active:scale-95 transition-all no-underline block mt-auto">
                Upgrade to Planner
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

