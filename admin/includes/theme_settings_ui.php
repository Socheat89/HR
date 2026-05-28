<?php
$themeConfigPath = __DIR__ . '/theme_config.json';
$currentTheme = 'default';
if (file_exists($themeConfigPath)) {
    $configData = json_decode(file_get_contents($themeConfigPath), true);
    $currentTheme = $configData['theme'] ?? 'default';
}

$themes = [
    'default' => [
        'name' => 'Default (ស្តង់ដារ)', 
        'icon' => 'fas fa-columns', 
        'color' => 'bg-gray-200',
        'default_bg' => ''
    ],
    'kny' => [
        'name' => 'ចូលឆ្នាំខ្មែរ (Khmer New Year)', 
        'icon' => 'https://i.ibb.co/qFRZ8SCK/khmer-new-year.png', 
        'color' => 'bg-yellow-100/50',
        'default_bg' => 'https://i.ibb.co/RKMS4tb/khmer-new-year-bg-1770518313913.jpg'
    ],
    'pb' => [
        'name' => 'ភ្ជុំបិណ្ឌ (Pchum Ben)', 
        'icon' => 'fas fa-place-of-worship', 
        'color' => 'bg-orange-200',
        'default_bg' => 'https://i.ibb.co/S4dYb35p/khmer-new-year-bg-1770518389358.jpg'
    ],
    'cny' => [
        'name' => 'ចូលឆ្នាំចិន (Chinese New Year)', 
        'icon' => 'https://i.ibb.co/G4K8Mv36/chinese-new-year.png', 
        'color' => 'bg-red-100/50',
        'default_bg' => 'https://img.freepik.com/premium-photo/copyspace-chinese-new-year-background-with-oriental-fans-chinese-lanterns-red-gold_780838-15759.jpg'
    ],
    'wf' => [
        'name' => 'បុណ្យអុំទូក (Water Festival)', 
        'icon' => 'fas fa-water', 
        'color' => 'bg-blue-200',
        'default_bg' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/c/c5/Cambodian_Water_Festival_-_Bon_Om_Touk.jpg/1280px-Cambodian_Water_Festival_-_Bon_Om_Touk.jpg'
    ],
    'kb' => [
        'name' => 'បុណ្យចម្រើនព្រះជន្ម (King\'s Birthday)', 
        'icon' => 'fas fa-crown', 
        'color' => 'bg-amber-200',
        'default_bg' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/f/f6/Royal_Palace_Phnom_Penh_at_night.jpg/1280px-Royal_Palace_Phnom_Penh_at_night.jpg'
    ],
    'indy' => [
        'name' => 'បុណ្យឯករាជ្យ (Independence Day)', 
        'icon' => 'fas fa-monument', 
        'color' => 'bg-purple-200',
        'default_bg' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/c/c3/Independence_Monument_Phnom_Penh.jpg/1280px-Independence_Monument_Phnom_Penh.jpg'
    ],
];
?>

<section class="mt-8">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-accent-hover mb-1"><i class="fas fa-palette mr-2"></i>ការកំណត់ Theme (រូបរាង)</h2>
        <p class="text-text-secondary">ផ្លាស់ប្តូររូបរាងនៃទំព័រដើម (Homes.php) តាមរដូវកាលនីមួយៗ</p>
    </div>

    <div class="settings-card p-6 bg-white rounded-2xl shadow-sm">
        <form method="POST" action="update_theme.php">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php foreach ($themes as $key => $theme): ?>
                    <label class="cursor-pointer group relative block">
                        <input type="radio" name="theme" value="<?php echo $key; ?>" 
                               data-default-bg="<?php echo $theme['default_bg']; ?>"
                               class="peer sr-only theme-radio" <?php echo ($currentTheme === $key) ? 'checked' : ''; ?>>
                        
                        <!-- Card UI -->
                        <div class="p-4 rounded-2xl border-2 border-slate-100 bg-white transition-all duration-300 
                                    group-hover:border-primary/30 group-hover:shadow-md
                                    peer-checked:border-primary peer-checked:bg-primary/[0.02] peer-checked:shadow-lg peer-checked:ring-1 peer-checked:ring-primary/20">
                            
                            <!-- Theme Preview Icon Box -->
                            <div class="aspect-video rounded-xl mb-4 <?php echo $theme['color']; ?> flex items-center justify-center shadow-inner group-hover:scale-105 transition-transform duration-500 overflow-hidden p-4">
                                <?php if (strpos($theme['icon'], 'http') === 0): ?>
                                    <img src="<?php echo $theme['icon']; ?>" class="w-20 h-20 object-contain filter drop-shadow-md">
                                <?php else: ?>
                                    <i class="<?php echo $theme['icon']; ?> text-4xl text-gray-700/40"></i>
                                <?php endif; ?>
                            </div>

                            <div class="flex items-center justify-between mt-2">
                                <div class="flex flex-col">
                                    <span class="font-black text-slate-800 tracking-tight text-sm"><?php echo $theme['name']; ?></span>
                                    <span class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-0.5"><?php echo $key === 'default' ? 'Standard' : 'Seasonal'; ?></span>
                                </div>
                                
                                <!-- Custom Radio Indicator -->
                                <div class="w-6 h-6 rounded-full border-2 border-slate-200 peer-checked:border-primary peer-checked:bg-primary flex items-center justify-center transition-all">
                                    <div class="w-2 h-2 bg-white rounded-full opacity-0 peer-checked:opacity-100 transition-opacity"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Active Badge -->
                        <div class="absolute -top-2 -right-2 opacity-0 peer-checked:opacity-100 transition-opacity">
                            <span class="bg-primary text-white text-[9px] font-black px-2 py-1 rounded-lg shadow-lg uppercase tracking-widest">Active</span>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>

            <!-- Background Image Section -->
            <div class="mt-10 p-6 bg-slate-50 rounded-2xl border border-slate-100">
                <h3 class="text-sm font-black text-slate-700 uppercase tracking-widest mb-4 flex items-center gap-2">
                    <i class="fas fa-image text-primary"></i> រូបភាពផ្ទៃក្រោយ (Background Image)
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="md:col-span-2">
                        <label class="form-label text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2 block">Link រូបភាព (Image URL)</label>
                        <div class="relative group mb-4">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-primary transition-colors">
                                <i class="fas fa-link"></i>
                            </div>
                            <input type="text" name="custom_image" id="custom_image_input" value="<?php echo htmlspecialchars($configData['custom_image'] ?? ''); ?>" 
                                   class="form-input pl-11 py-3.5 bg-white border-2 border-slate-200 focus:border-primary rounded-xl transition-all w-full" 
                                   placeholder="បញ្ចូល Link រូបភាពនៅទីនេះ (ឧ. https://example.com/bg.jpg)">
                        </div>
                        
                        <!-- History Section -->
                        <?php if (!empty($configData['history'])): ?>
                        <div class="mt-4">
                            <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2 block flex items-center gap-2">
                                <i class="fas fa-history"></i> ប្រវត្តិរូបភាពដែលធ្លាប់ប្រើ
                            </label>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($configData['history'] as $histUrl): ?>
                                    <div onclick="selectHistoryImage('<?php echo htmlspecialchars($histUrl); ?>')" 
                                         class="w-12 h-12 rounded-lg border border-slate-200 overflow-hidden cursor-pointer hover:ring-2 hover:ring-primary hover:scale-105 transition-all relative group"
                                         title="<?php echo htmlspecialchars($histUrl); ?>">
                                        <img src="<?php echo htmlspecialchars($histUrl); ?>" class="w-full h-full object-cover">
                                        <div class="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors"></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <p class="text-[10px] text-slate-400 mt-3 italic font-medium">* រូបភាពនឹងផ្លាស់ប្តូរដោយស្វ័យប្រវត្តិតាមការជ្រើសរើស Theme ខាងលើ ប៉ុន្តែអ្នកនៅតែអាចកែប្រែវាបាន។</p>
                    </div>
                    
                    <div>
                        <label class="form-label text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2 block">Preview</label>
                        <div id="bg-preview" class="aspect-video rounded-xl border-2 border-dashed border-slate-200 bg-slate-100 flex items-center justify-center overflow-hidden relative group">
                            <?php if (!empty($configData['custom_image'])): ?>
                                <img src="<?php echo htmlspecialchars($configData['custom_image']); ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <i class="fas fa-image text-2xl text-slate-300"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                function updatePreview(url) {
                    const preview = document.getElementById('bg-preview');
                    if (url) {
                        preview.innerHTML = `<img src="${url}" class="w-full h-full object-cover" onerror="this.onerror=null; this.src=''; this.parentElement.innerHTML='<i class=\'fas fa-exclamation-triangle text-2xl text-amber-500\'></i>';">`;
                    } else {
                        preview.innerHTML = `<i class="fas fa-image text-2xl text-slate-300"></i>`;
                    }
                }

                function selectHistoryImage(url) {
                    const input = document.getElementById('custom_image_input');
                    input.value = url;
                    updatePreview(url);
                    
                    // Visual feedback
                    input.classList.add('ring-2', 'ring-primary', 'ring-offset-1');
                    setTimeout(() => {
                        input.classList.remove('ring-2', 'ring-primary', 'ring-offset-1');
                    }, 500);
                }

                // Listener for Theme Selection
                document.querySelectorAll('.theme-radio').forEach(radio => {
                    radio.addEventListener('change', function() {
                        if (this.checked) {
                            const defaultBg = this.getAttribute('data-default-bg');
                            const input = document.getElementById('custom_image_input');
                            input.value = defaultBg;
                            updatePreview(defaultBg);
                        }
                    });
                });

                // Listener for Manual Input
                document.getElementById('custom_image_input').addEventListener('input', function() {
                    updatePreview(this.value);
                });
            </script>
            
            <div class="mt-10 flex flex-col md:flex-row items-center justify-between p-6 bg-slate-50 rounded-2xl border border-slate-100 gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-primary/10 text-primary rounded-full flex items-center justify-center">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <p class="text-xs text-slate-500 font-medium m-0 leading-relaxed">គន្លឹះ៖ ការផ្លាស់ប្តូរនេះនឹងជះឥទ្ធិពលភ្លាមៗទៅលើទំព័រដើម (Homes.php) សម្រាប់បុគ្គលិកទាំងអស់។</p>
                </div>
                <button type="submit" class="btn-base bg-primary hover:bg-primary-dark text-white px-10 py-3.5 rounded-xl shadow-xl hover:shadow-2xl transform hover:-translate-y-1 transition-all font-black text-sm flex items-center gap-3">
                    <i class="fas fa-save"></i> រក្សាទុកការផ្លាស់ប្តូរ
                </button>
            </div>
        </form>
    </div>
</section>
