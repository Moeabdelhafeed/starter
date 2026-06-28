<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Loader2, Sun, Moon } from 'lucide-vue-next';
import Button from '@/components/ui/button/Button.vue';
import SettingsCard from '@/components/dev-setting/SettingsCard.vue';

const { t } = useI18n();

const props = defineProps({
    lightColors: Object,
    darkColors: Object,
});

// Convert oklch to approximate hex for the color picker display
const oklchToHex = (oklch) => {
    if (!oklch || !oklch.startsWith('oklch')) return '#000000';
    const match = oklch.match(/oklch\(([\d.%]+)\s+([\d.]+)\s+([\d.]+)\)/);
    if (!match) return '#000000';

    let L = parseFloat(match[1]);
    if (match[1].includes('%')) L = L / 100;
    const C = parseFloat(match[2]);
    const H = parseFloat(match[3]);

    const hRad = (H * Math.PI) / 180;
    const a = C * Math.cos(hRad);
    const b = C * Math.sin(hRad);

    const l_ = L + 0.3963377774 * a + 0.2158037573 * b;
    const m_ = L - 0.1055613458 * a - 0.0638541728 * b;
    const s_ = L - 0.0894841775 * a - 1.2914855480 * b;

    const l = l_ * l_ * l_;
    const m = m_ * m_ * m_;
    const s = s_ * s_ * s_;

    let r = +4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s;
    let g = -1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s;
    let bl = -0.0041960863 * l - 0.7034186147 * m + 1.7076147010 * s;

    const toSrgb = (c) => {
        c = Math.max(0, Math.min(1, c));
        return c <= 0.0031308 ? 12.92 * c : 1.055 * Math.pow(c, 1 / 2.4) - 0.055;
    };

    r = Math.round(toSrgb(r) * 255);
    g = Math.round(toSrgb(g) * 255);
    bl = Math.round(toSrgb(bl) * 255);

    return '#' + [r, g, bl].map(v => Math.max(0, Math.min(255, v)).toString(16).padStart(2, '0')).join('');
};

// Light colors
const lightHex = ref({});
Object.entries(props.lightColors || {}).forEach(([key, val]) => {
    lightHex.value[key] = oklchToHex(val);
});

const lightForm = useForm({ colors: {}, mode: 'light' });

const submitLight = () => {
    lightForm.colors = { ...lightHex.value };
    lightForm.put(route('dev_settings.colors'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['lightColors', 'success', 'error'],
    });
};

// Dark colors
const darkHex = ref({});
Object.entries(props.darkColors || {}).forEach(([key, val]) => {
    darkHex.value[key] = oklchToHex(val);
});

const darkForm = useForm({ colors: {}, mode: 'dark' });

const submitDark = () => {
    darkForm.colors = { ...darkHex.value };
    darkForm.put(route('dev_settings.colors'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['darkColors', 'success', 'error'],
    });
};
</script>

<template>
    <div class="space-y-5">
        <!-- Light Theme Colors -->
        <SettingsCard :title="t('light_theme_colors')" :description="t('theme_colors_desc')">
            <template #icon><Sun class="size-5 text-yellow-500" /></template>

            <form @submit.prevent="submitLight" class="space-y-6">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="(value, varName) in lightColors" :key="varName" class="space-y-2">
                        <label class="block text-sm font-medium text-foreground">{{ varName }}</label>
                        <div class="flex items-center gap-3">
                            <input type="color" :value="lightHex[varName]"
                                @input="lightHex[varName] = $event.target.value"
                                class="h-10 w-14 cursor-pointer rounded-lg border border-input p-1" />
                            <div class="flex flex-1 items-center rounded-lg border border-input px-3 py-2">
                                <span class="font-mono text-sm text-foreground">{{ lightHex[varName] }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pt-4">
                    <Button type="submit" :disabled="lightForm.processing">
                        <Loader2 v-if="lightForm.processing" class="me-2 h-4 w-4 animate-spin" />
                        {{ lightForm.processing ? t('saving') : t('save_colors') }}
                    </Button>
                </div>
            </form>
        </SettingsCard>

        <!-- Dark Theme Colors -->
        <SettingsCard :title="t('dark_theme_colors')" :description="t('theme_colors_desc')">
            <template #icon><Moon class="size-5 text-indigo-500" /></template>

            <form @submit.prevent="submitDark" class="space-y-6">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="(value, varName) in darkColors" :key="varName" class="space-y-2">
                        <label class="block text-sm font-medium text-foreground">{{ varName }}</label>
                        <div class="flex items-center gap-3">
                            <input type="color" :value="darkHex[varName]"
                                @input="darkHex[varName] = $event.target.value"
                                class="h-10 w-14 cursor-pointer rounded-lg border border-input p-1" />
                            <div class="flex flex-1 items-center rounded-lg border border-input px-3 py-2">
                                <span class="font-mono text-sm text-foreground">{{ darkHex[varName] }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pt-4">
                    <Button type="submit" :disabled="darkForm.processing">
                        <Loader2 v-if="darkForm.processing" class="me-2 h-4 w-4 animate-spin" />
                        {{ darkForm.processing ? t('saving') : t('save_colors') }}
                    </Button>
                </div>
            </form>
        </SettingsCard>
    </div>
</template>
