# PixelBase SCSS Architecture Documentation

## Overview
This document provides a comprehensive guide to the SCSS architecture used in the PixelBase WordPress theme. The system is designed with modularity, maintainability, and consistency in mind.

## File Structure

```
app/scss/
├── main.scss              # Master import file
├── vars.scss              # Variables and imports
├── base.scss              # Global element styles
├── misc.scss              # Miscellaneous styles
├── font-styles.scss       # Typography mapping system
├── mixins.scss            # Legacy mixin import
├── components/            # UI components
│   ├── menu.scss
│   └── gravity-forms.scss
├── layout/               # Layout-specific styles
│   ├── header.scss
│   ├── footer.scss
│   └── blog.scss
├── includes/             # Block/ACF component styles
│   ├── hero.scss
│   ├── content-split.scss
│   ├── text-block.scss
│   └── contact-form-block.scss
├── woocommerce/          # WooCommerce specific styles
│   ├── archive-product.scss    # Product archive/shop page
│   ├── single-product.scss     # Individual product pages
│   ├── cart.scss               # Cart page styles
│   ├── checkout.scss           # Checkout process
│   └── my-account.scss         # Customer account area
├── global/               # Global utilities
│   └── animations.scss
└── mixins/               # Organized mixin library
    ├── _index.scss       # Master mixin imports
    ├── _functions.scss   # Core functions
    ├── _responsive.scss  # Breakpoint utilities
    ├── _typography.scss  # Text utilities
    ├── _layout.scss      # Layout mixins
    ├── _buttons.scss     # Button styles
    ├── _forms.scss       # Form/Gravity Forms
    ├── _utilities.scss   # General utilities
    └── _grid.scss        # Grid systems
```

## Core System Architecture

### 1. Variables System (`vars.scss`)

#### Color Palettes
The theme uses a systematic color approach with multiple palettes:

```scss
// Primary Palette 
$primary-10: #e5ebff;    // Lightest
$primary-base: #315cfe;  // Brand primary
$primary-100: #081c33;   // Darkest

// Secondary Palette 
$secondary-base: #f4531f;

// Grey Palette
$grey-base: #cbcbcb;

// Status Colors
$error-color: #d63638;
$success-color: #00a32a;
```

#### Typography Variables
```scss
$font: "Poppins", sans-serif;
$heading-font: "Poppins", sans-serif;
```

#### Responsive Breakpoints
```scss
// All breakpoints are min-width based for mobile-first approach
$s-mobile: "(min-width: 540px)";   // Small mobile up
$mobile: "(min-width: 768px)";     // Mobile/tablet up  
$s-tablet: "(min-width: 992px)";   // Small tablet up
$tablet: "(min-width: 1024px)";    // Tablet up
$desktop: "(min-width: 1200px)";   // Desktop up
```

**Usage Options:**
```scss
// Method 1: Direct media query (legacy)
@media #{$tablet} { /* styles */ }

// Method 2: Mixin system (recommended)
@include breakpoint(tablet) { /* styles */ }
@include breakpoint(mobile) { /* styles */ }
@include breakpoint(desktop) { /* styles */ }
```

#### Utility Variables
```scss
$max-width: rem-calc(1200);      // Container max-width
$shadow: rem-calc(0 5 10 0) rgba(0,0,0,0.3);
$ease: all 0.3s ease-in-out;     // Standard transition
```

### 2. Typography System (`font-styles.scss`)

#### Font Style Mapping
The theme uses a comprehensive typography mapping system with responsive variants:

```scss
$font-styles: (
    hero: (
        large: (
            font-size: rem-calc(72), 
            font-weight: 300, 
            line-height: 115%
        ),

        small: (
            font-size: rem-calc(48), 
            font-weight: 300, 
            line-height: 115%
        )
    ),
    heading-1: (
        large: (
            font-size: rem-calc(56), 
            font-weight: 300, 
            line-height: 115%
        ),

        small: (
            font-size: rem-calc(48), 
            font-weight: 300, 
            line-height: 115%
        )
    ),
    // ... additional styles
);
```

#### Usage
Apply typography styles using the mixin:
```scss
.my-heading {
    @include font-style(heading-2);
}
```

This automatically applies:
- Small screen styles by default
- Large screen styles via media query
- Consistent typography across the theme

### 3. Mixin System (`mixins/`)

#### Core Functions (`_functions.scss`)
```scss
// Convert pixels to rem units
@function rem-calc($value) {
    @return #{math.div($value, 16)}rem;
}
```

#### Responsive Mixins (`_responsive.scss`)
```scss
// Now supports all breakpoints from vars.scss
@media #{$s-mobile} {} //540px
@media #{$mobile} {} //768px
@media #{$s-tablet} {} //992px
@media #{$tablet} {} //1024px 
@media #{$desktop} {} //1200px
```

#### Layout Mixins (`_layout.scss`)
```scss
// Flex utilities
@include flex-center();           // Centers content
@include flex-between();          // Space between alignment
@include absolute-image-cover();  // Full coverage images
@include post-card-base();        // Standard card styling
```

#### Button Mixins (`_buttons.scss`)
```scss
// Pre-defined button styles
@include primary-button();        // Main CTA style
@include white-button();          // Secondary style
@include outline-button();        // Outlined variant
@include text-button();           // Text-only button
```

#### Form Mixins (`_forms.scss`)
Comprehensive Gravity Forms styling system:

```scss
// Auto-style complete forms
@include gform-wrapper-styles();

// Block-specific form styling
@include contact-form-block();
@include footer-form-block();
@include newsletter-form-block();
```

#### Utility Mixins (`_utilities.scss`)
```scss
@include card-shadow();           // Consistent drop shadows
@include text-clamp(3);          // Truncate text to 3 lines
@include image-wrapper();         // Responsive image containers
@include post-content-base();     // Blog content styling
```

## Usage Patterns

### 1. Creating New Components

When creating a new component, follow this structure:

```scss
// components/_my-component.scss
@import '../vars';  // If not using main.scss

.my-component {
    @include flex-center();
    @include font-style(body-medium);
    
    .my-component__title {
        @include font-style(heading-3);
        color: $primary-base;
    }
    
    .my-component__button {
        @include primary-button();
    }
}
```

### 2. Responsive Design

Use the unified breakpoint system consistently:

```scss
.responsive-element {
    // Mobile-first approach
    padding: rem-calc(20);
    
    // Method 1: Mixin system (recommended)
    @media #{$mobile} {
        padding: rem-calc(30);
    }
    
    @media #{$tablet} {
        padding: rem-calc(40);
    }
    
    // Method 2: Direct media query (legacy - still works)
    @media #{$desktop} {
        padding: rem-calc(50);
    }
}
```

### 3. Working with Colors

Use the systematic color palette:

```scss
.my-element {
    background-color: $primary-10;    // Light variant
    border: 1px solid $primary-base;  // Brand color
    color: $primary-100;              // Dark variant
}
```

### 4. Typography Implementation

Always use the font-style mixin for consistency:

```scss
.content-area {
    h2 {
        @include font-style(heading-2);
        margin-bottom: rem-calc(20);
    }
    
    p {
        @include font-style(body-medium);
    }
}
```

## Best Practices

### 1. Import Order
Always maintain this import order in `main.scss`:
1. Base files (`base`, `misc`)
2. Layout files
3. Component files  
4. Include/block files
5. Global files

### 2. Naming Conventions
- Use BEM methodology for component classes
- Prefix theme-specific classes when needed
- Use descriptive variable names

### 3. Responsive Design
- Follow mobile-first approach
- Use the provided breakpoint mixins
- Test across all defined breakpoints

### 4. Performance
- Minimize nesting depth (max 3 levels)
- Use mixins to avoid code duplication
- Leverage the existing utility classes

### 5. Maintenance
- Keep mixins focused and single-purpose
- Document complex calculations
- Use the systematic color and typography scales

## Common Use Cases

### Creating a Card Component
```scss
.news-card {
    @include post-card-base();
    
    &__image {
        @include responsive-image(200);
    }
    
    &__content {
        padding: rem-calc(20);
        
        h3 {
            @include font-style(heading-4);
            margin-bottom: rem-calc(10);
        }
        
        p {
            @include font-style(body-small);
            @include text-clamp(3);
        }
    }
    
    &__button {
        @include outline-button();
        margin-top: rem-calc(15);
    }
}
```

### Styling a Gravity Form Block
```scss
.contact-section {
    @include contact-form-block($primary-10);
    
    // Form is automatically styled via the mixin
    // Add custom overrides if needed
    .gform_wrapper .gform_title {
        color: $primary-base;
    }
}
```

### Creating Responsive Grid
```scss
.product-grid {
    @include post-grid();  // 1-2-3 column responsive grid
    
    .product-item {
        @include post-card-base();
    }
}
```

## File Organization Guidelines

### When to Create New Files
- **Components**: UI elements used across multiple pages
- **Layout**: Page structure elements (header, footer, etc.)
- **Includes**: ACF block or template-specific styles
- **Global**: Site-wide utilities and animations

### Mixin Organization
- **Functions**: Core calculations and conversions
- **Responsive**: Breakpoint and media query utilities
- **Layout**: Positioning and display utilities
- **Typography**: Text-related mixins
- **Components**: Reusable component patterns
- **Utilities**: General-purpose helpers

## Troubleshooting

### Common Issues
1. **Missing rem-calc**: Always use `rem-calc()` for pixel values
2. **Inconsistent breakpoints**: Use the unified breakpoint system (either mixin or direct media queries)
3. **Color inconsistency**: Reference the systematic color palette
4. **Typography mismatch**: Use `font-style()` mixin instead of direct properties

### Debugging Tips
1. Check import order in `main.scss`
2. Verify mixin availability in current context
3. Use browser dev tools to trace compiled CSS
4. Check for conflicting specificity

## Benefits of This SCSS Architecture

This modular SCSS architecture delivers significant advantages for WordPress theme development and long-term maintenance. The systematic approach to color palettes, typography scales, and responsive breakpoints ensures visual consistency across the entire site while reducing the likelihood of design inconsistencies. The comprehensive mixin library dramatically improves developer productivity by providing pre-built solutions for common styling patterns like buttons, forms, and grid layouts, eliminating repetitive code and reducing development time. The mobile-first responsive system with unified breakpoints guarantees optimal display across all devices while maintaining clean, readable code. From a maintenance perspective, the organized file structure and clear naming conventions make it easy for team members to locate and modify specific components without affecting other parts of the theme. The use of systematic variables means global changes (like updating brand colors or typography) can be made in a single location and propagate throughout the entire codebase, significantly reducing the risk of errors and inconsistencies during updates or redesigns.

This architecture provides a scalable, maintainable approach to styling while ensuring consistency across the entire WordPress theme.
