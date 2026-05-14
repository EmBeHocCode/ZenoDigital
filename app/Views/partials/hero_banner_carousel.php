<?php
$heroBanners = is_array($heroBanners ?? null) ? array_values($heroBanners) : [];

if (!function_exists('hero_banner_href')) {
    function hero_banner_href(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (str_starts_with($url, '#') || preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }

        return base_url(ltrim($url, '/'));
    }
}
?>

<?php if ($heroBanners !== []): ?>
    <section class="home-hero-banner-section" aria-label="Banner khuyến mãi">
        <div class="container">
            <div id="homeHeroBannerCarousel" class="carousel slide home-hero-banner-carousel" data-bs-ride="carousel" data-bs-interval="5000" data-bs-touch="true">
                <?php if (count($heroBanners) > 1): ?>
                    <div class="carousel-indicators home-hero-banner-dots">
                        <?php foreach ($heroBanners as $index => $banner): ?>
                            <button
                                type="button"
                                data-bs-target="#homeHeroBannerCarousel"
                                data-bs-slide-to="<?= (int) $index ?>"
                                class="<?= $index === 0 ? 'active' : '' ?>"
                                aria-current="<?= $index === 0 ? 'true' : 'false' ?>"
                                aria-label="Slide <?= (int) ($index + 1) ?>"
                            ></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="carousel-inner">
                    <?php foreach ($heroBanners as $index => $banner): ?>
                        <?php
                        $title = (string) ($banner['title'] ?? 'Banner');
                        $subtitle = (string) ($banner['subtitle'] ?? '');
                        $image = basename((string) ($banner['image_path'] ?? ''));
                        $imageUrl = base_url('assets/images/slides/' . rawurlencode($image));
                        $href = hero_banner_href((string) ($banner['link_url'] ?? ''));
                        $tagName = $href !== '' ? 'a' : 'div';
                        ?>
                        <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                            <<?= $tagName ?>
                                class="home-hero-banner-slide <?= $href !== '' ? 'is-clickable' : '' ?>"
                                style="--hero-banner-image: url('<?= e($imageUrl) ?>');"
                                <?php if ($href !== ''): ?>href="<?= e($href) ?>"<?php endif; ?>
                                aria-label="<?= e($title) ?>"
                            >
                                <img src="<?= e($imageUrl) ?>" class="home-hero-banner-img" alt="<?= e($title) ?>">
                                <span class="visually-hidden"><?= e(trim($title . ' ' . $subtitle . ' ' . (string) ($banner['link_label'] ?? ''))) ?></span>
                            </<?= $tagName ?>>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (count($heroBanners) > 1): ?>
                    <button class="carousel-control-prev home-hero-banner-control" type="button" data-bs-target="#homeHeroBannerCarousel" data-bs-slide="prev" aria-label="Banner trước">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    </button>
                    <button class="carousel-control-next home-hero-banner-control" type="button" data-bs-target="#homeHeroBannerCarousel" data-bs-slide="next" aria-label="Banner tiếp theo">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </section>
<?php endif; ?>
