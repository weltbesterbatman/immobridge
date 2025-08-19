# ImmoBridge Bricks Builder Templates

This directory contains pre-built Bricks Builder templates for displaying property data from the ImmoBridge plugin.

## Templates Overview

### 1. Property List Template (`property-list-template.json`)

- **Type**: Archive Template
- **Purpose**: Displays a grid of properties with filtering options
- **Features**:
  - Responsive grid layout (3 columns on desktop, 1 on mobile)
  - Property filtering by type, city, and price range
  - Property cards with image, title, address, specs, and price
  - Pagination support
  - Hover effects and smooth transitions

### 2. Property Detail Template (`property-detail-template.json`)

- **Type**: Single Template
- **Purpose**: Displays detailed information for individual properties
- **Features**:
  - Two-column layout (main content + sidebar)
  - Image gallery with masonry layout
  - Comprehensive property specifications
  - Contact form integration
  - Energy efficiency information
  - Property features list
  - Responsive design

## Installation Instructions

### Step 1: Import Templates in Bricks Builder

1. Go to **Bricks → Templates** in your WordPress admin
2. Click **Import Template**
3. Upload the JSON files from this directory
4. The templates will be imported and ready to use

### Step 2: Assign Templates to Post Type

#### For Property List Template:

1. Go to **Bricks → Templates**
2. Find "ImmoBridge Property List" template
3. Click **Edit**
4. In Template Settings, set:
   - **Template Type**: Archive
   - **Post Type**: Properties (immo_property)
   - **Conditions**: Archive for immo_property

#### For Property Detail Template:

1. Go to **Bricks → Templates**
2. Find "ImmoBridge Property Detail" template
3. Click **Edit**
4. In Template Settings, set:
   - **Template Type**: Single
   - **Post Type**: Properties (immo_property)
   - **Conditions**: Single immo_property

### Step 3: Configure Dynamic Data

The templates use ImmoBridge Dynamic Data tags that are automatically registered when the plugin is active. Available tags include:

#### Basic Information

- `{immobridge_property_title}` - Property title
- `{immobridge_property_description}` - Property description
- `{immobridge_property_address}` - Full address
- `{immobridge_property_city}` - City
- `{immobridge_property_postal_code}` - Postal code

#### Price Information

- `{immobridge_property_price}` - Raw price value
- `{immobridge_property_price_formatted}` - Formatted price with currency
- `{immobridge_property_rent}` - Monthly rent
- `{immobridge_property_additional_costs}` - Additional costs

#### Property Details

- `{immobridge_property_living_area}` - Living area with m² unit
- `{immobridge_property_rooms}` - Number of rooms
- `{immobridge_property_bedrooms}` - Number of bedrooms
- `{immobridge_property_bathrooms}` - Number of bathrooms

#### Property Type & Status

- `{immobridge_property_type}` - Property type
- `{immobridge_property_status}` - Property status

#### Contact Information

- `{immobridge_property_contact_name}` - Contact person name
- `{immobridge_property_contact_phone}` - Contact phone
- `{immobridge_property_contact_email}` - Contact email

#### Media

- `{immobridge_property_featured_image}` - Featured image URL
- `{immobridge_property_gallery}` - Property gallery array

#### Energy Efficiency

- `{immobridge_property_energy_class}` - Energy efficiency class
- `{immobridge_property_energy_consumption}` - Energy consumption

#### Features

- `{immobridge_property_features}` - Property features array
- `{immobridge_property_equipment}` - Property equipment array

## Customization Guide

### Modifying Colors and Typography

1. **Edit Template**: Go to Bricks → Templates and edit the desired template
2. **Select Elements**: Click on individual elements to modify their styling
3. **Common Customizations**:
   - Change primary color from `#007cba` to your brand color
   - Modify typography settings in heading and text elements
   - Adjust spacing using margin and padding controls

### Adding More Filter Options

To add additional filters to the Property List Template:

1. **Edit Template**: Open the Property List Template in Bricks Builder
2. **Find Filter Section**: Locate the "Filter Row" element
3. **Duplicate Filter**: Copy an existing filter element (e.g., filter-type)
4. **Configure New Filter**:
   - Change the `filterKey` to the desired meta field
   - Update the placeholder text
   - Adjust styling as needed

### Modifying Grid Layout

#### Property List Grid:

- **Desktop**: Change `_gridTemplateColumns` from `repeat(auto-fit, minmax(350px, 1fr))` to your preferred layout
- **Mobile**: Modify the CSS media query for responsive behavior

#### Property Detail Layout:

- **Two Column**: Default is `2fr 1fr` (main content wider than sidebar)
- **Equal Columns**: Change to `1fr 1fr`
- **Single Column**: Change to `1fr` and remove sidebar

### Adding Custom Property Fields

1. **Register Field**: Add the field to your BricksIntegrationServiceProvider
2. **Add Dynamic Data Tag**: Create a new tag in the `registerDynamicDataTags` method
3. **Use in Template**: Add the tag to your template elements

Example:

```php
$tags['immobridge_property_custom_field'] = [
    'label' => __('Custom Field', 'immobridge'),
    'group' => 'immobridge',
    'provider' => 'immobridge',
    'callback' => [$this, 'getPropertyCustomField']
];
```

## Troubleshooting

### Dynamic Data Not Showing

1. **Check Plugin Activation**: Ensure ImmoBridge plugin is active
2. **Verify Bricks Theme**: Confirm Bricks theme is active
3. **Clear Cache**: Clear any caching plugins
4. **Check Post Type**: Ensure you're viewing immo_property posts

### Template Not Applying

1. **Check Conditions**: Verify template conditions are set correctly
2. **Template Priority**: Ensure no other templates are overriding
3. **Post Type Registration**: Confirm immo_property post type is registered

### Styling Issues

1. **CSS Conflicts**: Check for theme CSS conflicts
2. **Responsive Issues**: Test on different screen sizes
3. **Browser Compatibility**: Test in different browsers

## Advanced Customization

### Custom CSS

Add custom CSS in **Bricks → Settings → Custom Code → CSS**:

```css
/* Custom property card hover effects */
.immobridge-property-element:hover {
  transform: translateY(-5px);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
}

/* Custom price styling */
.property-price {
  background: linear-gradient(45deg, #2e7d32, #4caf50);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}
```

### Custom JavaScript

Add custom JavaScript in **Bricks → Settings → Custom Code → JavaScript**:

```javascript
// Custom property filtering
document.addEventListener("DOMContentLoaded", function () {
  // Add custom filter functionality
  const filters = document.querySelectorAll(".property-filter");
  filters.forEach((filter) => {
    filter.addEventListener("change", function () {
      // Custom filter logic
    });
  });
});
```

## Support

For support with these templates:

1. **Plugin Issues**: Check the ImmoBridge plugin documentation
2. **Bricks Issues**: Refer to Bricks Builder documentation
3. **Template Customization**: Modify templates according to your needs

## Version History

- **v1.0.0**: Initial release with Property List and Property Detail templates
  - Responsive grid layout
  - Dynamic data integration
  - Contact form support
  - Energy efficiency display
  - Mobile-optimized design
