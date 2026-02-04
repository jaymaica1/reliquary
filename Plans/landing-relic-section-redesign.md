# Plan: Landing Page Relic Section Redesign

## Overview
Replace the static "Relic Classes" section with two new dynamic features:
1. **Featured Relics Showcase** - Display highlighted relics with images
2. **Statistics Dashboard** - Show collection metrics

## Current State
- Location: `templates/home/landing.html.twig` (lines 30-57)
- Static content explaining First, Second, Third class relics
- Links to filtered relic index pages

---

## Feature 1: Featured Relics Showcase

### Backend Changes

#### 1.1 Update HomeController
- [ ] File: `src/Controller/HomeController.php`
- [ ] Add query to fetch featured/recent relics (3-6 items)
- [ ] Pass `featuredRelics` variable to template
- [ ] Consider criteria: most recent approved, has images, manually featured flag

```php
// Example query logic
$featuredRelics = $relicRepository->findBy(
    ['status' => 'approved'],
    ['createdAt' => 'DESC'],
    6
);
```

#### 1.2 Optional: Add Featured Flag to Relic Entity
- [ ] Add `isFeatured` boolean field to Relic entity (optional)
- [ ] Create migration
- [ ] Update admin interface to toggle featured status

### Frontend Changes

#### 1.3 Update Landing Template
- [ ] File: `templates/home/landing.html.twig`
- [ ] Replace or add section below hero
- [ ] Display relic cards with: image thumbnail, saint name, relic type, location

```twig
<!-- Featured Relics Section -->
<section class="featured-relics">
    <h2 class="section-title">{{ 'featured.title'|trans({}, 'landing') }}</h2>
    <div class="relics-grid">
        {% for relic in featuredRelics %}
            <a href="{{ path('app_relic_show', {'id': relic.id}) }}" class="relic-card">
                <div class="relic-image">
                    {% if relic.images|length > 0 %}
                        <img src="{{ image_url(relic.images.first.thumbnailFilename) }}" alt="{{ relic.saint.name }}">
                    {% endif %}
                </div>
                <div class="relic-info">
                    <h3>{{ relic.saint.name }}</h3>
                    <p>{{ relic.relicClass }} Class Relic</p>
                </div>
            </a>
        {% endfor %}
    </div>
</section>
```

#### 1.4 Add Styles
- [ ] File: `assets/styles/landing.css` (or equivalent)
- [ ] Style `.featured-relics` section
- [ ] Responsive grid layout (3 columns desktop, 2 tablet, 1 mobile)

#### 1.5 Add Translations
- [ ] File: `translations/landing.en.yaml` (and other locales)
- [ ] Add keys: `featured.title`, `featured.subtitle`

---

## Feature 2: Statistics Dashboard

### Backend Changes

#### 2.1 Create Statistics Service (Optional)
- [ ] File: `src/Service/StatisticsService.php`
- [ ] Methods to calculate and cache stats
- [ ] Consider caching for performance

#### 2.2 Update HomeController
- [ ] Add statistics queries:
  - Total approved relics count
  - Total saints with relics count
  - Total countries/locations count
  - Total contributors count (optional)
- [ ] Pass `stats` array to template

```php
$stats = [
    'relics' => $relicRepository->countApproved(),
    'saints' => $saintRepository->countWithRelics(),
    'countries' => $relicRepository->countDistinctCountries(),
];
```

#### 2.3 Add Repository Methods
- [ ] File: `src/Repository/RelicRepository.php`
  - [ ] `countApproved(): int`
  - [ ] `countDistinctCountries(): int`
- [ ] File: `src/Repository/SaintRepository.php`
  - [ ] `countWithRelics(): int`

### Frontend Changes

#### 2.4 Update Landing Template
- [ ] Add statistics section (can be above or below featured relics)

```twig
<!-- Statistics Section -->
<section class="stats-section">
    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-number">{{ stats.relics }}</span>
            <span class="stat-label">{{ 'stats.relics'|trans({}, 'landing') }}</span>
        </div>
        <div class="stat-card">
            <span class="stat-number">{{ stats.saints }}</span>
            <span class="stat-label">{{ 'stats.saints'|trans({}, 'landing') }}</span>
        </div>
        <div class="stat-card">
            <span class="stat-number">{{ stats.countries }}</span>
            <span class="stat-label">{{ 'stats.countries'|trans({}, 'landing') }}</span>
        </div>
    </div>
</section>
```

#### 2.5 Add Styles
- [ ] Style `.stats-section` with prominent numbers
- [ ] Consider animated counter effect (optional JS)

#### 2.6 Add Translations
- [ ] Add keys: `stats.relics`, `stats.saints`, `stats.countries`

---

## Implementation Order

1. **Phase 1: Statistics Dashboard** (simpler, no new entities)
   - Add repository count methods
   - Update controller
   - Add template section and styles
   - Add translations

2. **Phase 2: Featured Relics Showcase**
   - Update controller with relic query
   - Add template section and styles
   - Add translations

3. **Phase 3: Polish & Optional Enhancements**
   - Add caching for statistics
   - Add `isFeatured` flag for manual curation
   - Add animated counters
   - A/B test placement (above/below hero)

---

## Testing Checklist

- [ ] Statistics display correct counts
- [ ] Featured relics show with images
- [ ] Links navigate to correct relic/saint pages
- [ ] Responsive design works on mobile
- [ ] Translations work for all supported locales
- [ ] Performance acceptable (consider caching if slow)

---

## Decision: Keep or Remove Relic Classes?

**Options:**
1. **Replace entirely** - Remove relic classes section, use space for new features
2. **Move to footer or separate page** - Keep educational content accessible elsewhere
3. **Combine** - Show stats inline with relic class cards (e.g., "First Class - 234 relics")

**Recommendation:** Option 3 - Combine stats with relic classes for a hybrid approach that's both educational and dynamic.
