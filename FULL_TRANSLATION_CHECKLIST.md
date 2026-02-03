# Directorist + WPML: Full Translation Checklist

This document lists **what is translatable**, **what you must do in WPML**, and any **gaps** so you can make Directorist fully translated.

---

## 1. What YOU must do in WPML (your side)

### 1.1 WPML → Settings → Custom Fields / Term Meta

| Item | Setting to use | Why |
|------|----------------|-----|
| **`search_form_fields`** (Term Meta) | **Don't translate** | Integration translates labels/placeholders at runtime via String Translation. Translating the raw meta would break the form config. |
| **`submission_form_fields`** (Term Meta) | **Don't translate** | Same: integration translates add-listing fields/sections at runtime. |
| Other Directorist term meta (e.g. `submit_button_label`, `single_listing_page`, etc.) | **Don't translate** or **Copy** | Use **Translate** only for simple display-only text you are not translating elsewhere. |

### 1.2 WPML → String Translation

You (or a translator) must **add translations** for:

- **Directorist plugin domain (`directorist`)**  
  Directorist uses `__()`, `_e()`, etc. with text domain `directorist`. WPML scans the plugin and lists these. Translate them in **WPML → String Translation** (filter by domain `directorist`).

- **Integration domain (`directorist-wpml-integration`)**  
  Our plugin registers strings here. After you visit pages that use:
  - Search form
  - Add listing form
  - **Select2 dropdowns** (Category/Location: “No results found”, “Searching…”, “Loading more…”, etc. → all **`select2_*`** strings; see **JS_STRINGS_TRANSLATION.md**)
  - Directory type names
  - Blocks/widgets (with custom titles/text)
  - Options (when they are used on frontend)  
  …these strings appear in String Translation. Filter by domain **directorist-wpml-integration** and translate them.

- **Admin texts (`atbdp_option`)**  
  Listed in `wpml-config.xml` under `<admin-texts>`. They appear in **WPML → String Translation** (or Translation Editor). Translate so labels, buttons, email templates, etc. show in each language.

### 1.3 Translate content (pages, taxonomy terms, listings)

- **Pages**: All listings, Add listing, Dashboard, etc. — create translations for each language (or use WPML’s “Translate” for the page and assign the correct language).
- **Taxonomies**: Categories, Locations, Tags — translate terms in **WPML → Taxonomy Translation** (or when editing the term, set language and add translation).
- **Directory types** (`atbdp_listing_types`): Translate directory type names (e.g. “General”, “Rental”) so they show in the correct language. Integration helps display the translated name.
- **Listings (ATBDP post type)**: Translate each listing (title, content, custom fields marked “translate” in wpml-config) via WPML’s post translation.

### 1.4 Language switcher and URLs

- Integration adjusts **Directorist-specific URLs** (all listings, category, location, tag, author, pagination, checkout, etc.) so the language switcher points to the correct translated URL. No extra setting needed if WPML and the integration are active.

---

## 2. What the integration plugin makes translatable (automatically)

| Area | How it works | Fully translatable? |
|------|----------------|----------------------|
| **Search form fields** | `Search_Form_Field_Translation`: hooks `directorist_template`, translates `$args['data']` (label, placeholder, description, options, pricing min/max, radius min/max) per directory. Strings: `search_form_dir_{id}_field_{slug}_{property}`. | Yes, once strings are translated in String Translation. |
| **Add listing form** | `Add_Listing_Form_Translation`: hooks `directorist_form_field_data`, `directorist_section_template`, `atbdp_add_listing_page_template`. Translates field labels/placeholders/options and section labels (sidebar + headers). | Yes, once strings are translated. |
| **Directorist options** | `Option_Translation`: hooks `directorist_option`. Translates display strings from `get_directorist_option()` (skips query/ID keys). `Settings_Registration` registers option strings when saved. | Yes, for options that are display strings (and in admin-texts where configured). |
| **Directory type names** | `Directory_Translation`: hooks `directorist_directories_for_template`, `get_terms`, `term_name`. Translates directory type labels. | Yes. |
| **Gutenberg blocks** | `Block_Widget_Translation`: hooks `render_block_data` + `render_block`. Translates block attributes (e.g. search bar title, header title, button text). | Yes, for attributes listed in plugin + wpml-config. |
| **Elementor widgets** | `Block_Widget_Translation`: hooks `elementor/widget/render_content`. Translates widget settings (titles, labels, etc.) per widget. | Yes, for widgets/fields in plugin + wpml-config. |
| **Listings query** | `Query_Filtering`: ensures only current-language listings; translates `tax_query` term IDs. `Search_Form_Filter`: filters categories/locations/tags in search by language. | Yes (language filtering). |
| **Listing count** | `Listing_Count_Filter`: keeps counts per language. | Yes. |
| **Permalinks / URLs** | `Filter_Permalinks`: all listings, category/location/tag singles, author, pagination, checkout, edit listing, etc. | Yes. |
| **Emails** | `Email_Translation`: switches WPML language to the listing’s language before sending so Directorist’s email templates use the right language. | Yes, if email template strings are translated (Directorist + admin texts). |
| **Listing duplication/translation** | `Listings_Actions`: keeps directory type when copying/translating listings via WPML. | Yes. |
| **REST API** | `REST_API`: sets content language for Directorist REST requests. | Yes for language context. |

---

## 3. Directorist plugin (without integration)

| Source | Domain | Handled by |
|--------|--------|------------|
| All PHP strings in Directorist using `__()`, `_e()`, `esc_html__()`, etc. | `directorist` | WordPress/WPML — appear in **String Translation** under domain `directorist`. You must translate them. |
| Custom post type `at_biz_dir` | — | wpml-config: `translate="1"`. WPML treats listings as translatable. |
| Taxonomies (category, location, tags, listing types) | — | wpml-config: `translate="1"`. Use Taxonomy Translation. |
| Custom fields (listing meta) | — | wpml-config: copy/translate/ignore per field. |
| Admin texts (atbdp_option keys) | atbdp_option | wpml-config + Option_Translation. Translate in String Translation. |
| Gutenberg blocks / Elementor widgets | — | wpml-config declares keys; Block_Widget_Translation translates at runtime. |

---

## 4. Gaps / “Not fully translatable” without extra work

| Item | Why | What you can do |
|------|-----|------------------|
| **Search form: directory_id** | String names use `search_form_dir_{directory_id}_...`. If the directory type has a **different term ID per language** (WPML taxonomy translation), the key differs and translations registered for one ID won’t apply for the other. | Use the same directory type ID for string naming across languages (e.g. map to default-language term ID in the integration), or add translations for each language’s directory ID. |
| **Hardcoded or third-party strings** | Any text not coming from Directorist or the integration (e.g. theme, another plugin) | Translate in String Translation under the relevant domain or use WPML’s theme/plugin scan. |
| **JS-rendered text** | Text only in JavaScript (e.g. “Load more”) | Directorist usually passes these from PHP; if any are only in JS, they need to be localized (wp_localize_script) and then translated. |
| **New block/widget attributes** | New Directorist blocks or new attributes added by Directorist | Add them to `Block_Widget_Translation` and to `wpml-config.xml` in the integration. |

---

## 5. Quick checklist: “Is everything translatable?”

- [ ] **WPML**: Term Meta for `search_form_fields` and `submission_form_fields` = **Don't translate**.
- [ ] **WPML → String Translation**: Translated all strings for domain **directorist** (Directorist core).
- [ ] **WPML → String Translation**: Translated all strings for domain **directorist-wpml-integration** (search form, add listing, directory names, options, blocks/widgets).
- [ ] **WPML → String Translation**: Translated **admin texts** (atbdp_option) used on frontend and in emails.
- [ ] **WPML**: Translated **pages** (All Listings, Add Listing, Dashboard, etc.) per language.
- [ ] **WPML**: Translated **taxonomies** (categories, locations, tags) and **directory types**.
- [ ] **WPML**: Translated **listings** (post type at_biz_dir) and their translatable custom fields.
- [ ] **Content**: Each language has the same **structure** (e.g. search form block/widget present on the translated “All Listings” page) so the integration can translate the strings.

If all boxes are done, Directorist is fully set up for translation; the integration covers runtime translation of search form, add listing form, options, directory names, blocks/widgets, queries, permalinks, and emails.
