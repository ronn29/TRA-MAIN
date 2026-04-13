# Career Resource Recommendation System

## Overview

The Career Resource Recommendation System provides curated learning resources (courses, certifications, learning paths) for each career recommendation. The system intelligently organizes and prioritizes resources to help students develop the skills they need for their recommended career paths.

## Key Features

### 1. **Enhanced Resource Data Structure**

Resources are now organized with detailed metadata:
- **Resource Name**: Display name of the course/certification
- **Resource URL**: Direct link to the resource
- **Resource Type**: Classification (course, certification, learning_path, other)
- **Resource Provider**: Organization offering the resource (Google, Coursera, edX, etc.)
- **Is Free**: Boolean flag indicating if the resource is free
- **Display Order**: Manual priority ordering
- **Is Active**: Enable/disable resources without deletion

### 2. **Intelligent Relevance Scoring**

Resources are automatically scored and sorted by relevance using multiple criteria:

#### Scoring Factors:

| Factor | Points | Rationale |
|--------|--------|-----------|
| **Display Order** | 100 - (order × 5) | Base priority set by administrators |
| **Free Resources** | +30 | Critical for student accessibility |
| **Certifications** | +25 | Career-critical credentials |
| **Premium Providers** | +20 | Google, Microsoft, AWS, IBM, Oracle, Cisco, CompTIA, ISTQB, PMI |
| **Trusted Platforms** | +15 | Coursera, edX, MIT, Harvard, Stanford, freeCodeCamp |
| **Learning Paths** | +10 | Comprehensive structured learning |
| **Courses** | +5 | Single topic deep dives |

#### Premium Providers:
- **Google, Microsoft, AWS, IBM, Oracle, Cisco**: Industry-leading tech companies
- **CompTIA, ISTQB, PMI**: Recognized certification bodies
- **IEEE, ACM**: Professional organizations

#### Trusted Educational Platforms:
- **Coursera, edX**: Leading MOOC platforms
- **MIT, Harvard, Stanford**: Top universities
- **freeCodeCamp**: Free comprehensive coding curriculum

### 3. **Resource Grouping**

Resources are organized into multiple views:

```php
[
    'all' => [...],              // All resources
    'certifications' => [...],   // Filtered by type
    'courses' => [...],          // Filtered by type
    'learning_paths' => [...],   // Filtered by type
    'free' => [...]              // Only free resources
]
```

### 4. **Enhanced Display System**

#### Visual Enhancements:
- **Color-coded badges** for quick identification:
  - 🟢 **Free** - Green badge (emphasizes accessibility)
  - 🟡 **Certification** - Yellow badge (highlights credentials)
  - 🔵 **Provider** - Blue badge (shows credibility)

- **Hover effects**: Cards elevate and highlight on hover
- **Responsive layout**: Adapts to all screen sizes
- **Smart truncation**: Shows top 6 resources per career with "more available" indicator

#### Badge Color Scheme:

```
Free Resources:
  - Background: #d4edda (light green)
  - Text: #155724 (dark green)
  - Icon: la-gift

Certifications:
  - Background: #fff3cd (light yellow)
  - Text: #856404 (dark yellow)
  - Icon: la-certificate

Premium Providers:
  - Background: #cce5ff (light blue)
  - Text: #004085 (dark blue)
  - Icon: la-building

Default:
  - Background: #e9ecef (light gray)
  - Text: #6c757d (gray)
  - Icon: la-tag
```

### 5. **Student-Friendly Display**

Resources are presented with:
- Clear resource names without clutter
- Prominent badges showing key information
- Direct external links opening in new tabs
- Visual hierarchy with icons and spacing
- Helpful tips about prioritizing resources

## API Functions

### `getCareerResources($conn, $careerNames)`

Returns detailed resource data grouped by type.

**Parameters:**
- `$conn`: Database connection
- `$careerNames`: Array of career names

**Returns:**
```php
[
    'Career Name' => [
        'all' => [
            [
                'name' => 'Resource Name',
                'url' => 'https://...',
                'type' => 'certification',
                'provider' => 'Google',
                'is_free' => true,
                'display_order' => 1
            ],
            ...
        ],
        'certifications' => [...],
        'courses' => [...],
        'learning_paths' => [...],
        'free' => [...]
    ]
]
```

### `calculateResourceRelevance($resource)`

Calculates relevance score for a resource.

**Parameters:**
- `$resource`: Resource array with metadata

**Returns:** Integer score (higher = more relevant)

### `getSimpleCareerResources($conn, $careerNames)`

Returns simplified, sorted resource list for display (backward compatible).

**Parameters:**
- `$conn`: Database connection
- `$careerNames`: Array of career names

**Returns:**
```php
[
    'Career Name' => [
        'Resource Name (Free • Certification • Provider)' => 'https://...',
        ...
    ]
]
```

## Database Schema

```sql
CREATE TABLE career_resources_tbl (
    resource_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    career_name VARCHAR(255) NOT NULL,
    resource_name VARCHAR(255) NOT NULL,
    resource_url TEXT NOT NULL,
    resource_type ENUM('course', 'certification', 'learning_path', 'other') DEFAULT 'other',
    resource_provider VARCHAR(100),
    is_free TINYINT(1) DEFAULT 0,
    display_order INT(11) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_career_name (career_name),
    KEY idx_active (is_active)
);
```

## URL Validation

A Python script (`db/validate_urls.py`) is provided to check resource URLs:

```bash
cd db
pip install requests
python validate_urls.py
```

This script:
- Checks all URLs for accessibility
- Identifies broken links (404 errors)
- Detects redirected URLs
- Generates a detailed report
- Highlights URLs that need updating

## Best Practices

### For Administrators:

1. **Keep URLs Current**: Run validation script quarterly
2. **Prioritize Free Resources**: Set `is_free = 1` for accessible resources
3. **Use Display Order**: Set lower numbers for higher priority (1 is highest)
4. **Add Provider Info**: Always include reputable provider names
5. **Set Resource Types**: Properly categorize as course/certification/learning_path
6. **Update Broken Links**: Replace URLs returning 404 errors immediately

### For Resource Selection:

1. **Certifications First**: Industry-recognized credentials (Google, AWS, CompTIA)
2. **Free Resources**: Prioritize accessible learning (freeCodeCamp, edX free courses)
3. **Reputable Providers**: Stick to known platforms (Coursera, Udacity, official docs)
4. **Comprehensive Paths**: Include structured learning paths for beginners
5. **Diverse Sources**: Mix MOOCs, official documentation, and practice platforms

### Recommended Resource Mix per Career:

- 1-2 certifications (at least one free prep resource)
- 2-3 comprehensive courses (mix of free and paid)
- 1-2 learning paths (structured curricula)
- 1-2 practical resources (coding practice, documentation)

## Usage in Student Views

### career_prediction.php
Shows resources for top 5 recommended careers with full detail view.

### view_results.php
Displays resources in historical assessment results.

Both pages now feature:
- Enhanced visual design
- Smart resource prioritization
- Helpful tips for students
- Responsive card layouts
- Smooth hover interactions

## Performance Optimizations

1. **Limited Display**: Shows only top 6 resources per career (prevents overwhelming students)
2. **Top 5 Careers Only**: Fetches resources only for top career matches
3. **Efficient Queries**: Single query with proper indexing
4. **Cached Scoring**: Relevance calculated once during fetch
5. **Optimized Sorting**: In-memory sorting after database fetch

## Future Enhancements

### Potential Improvements:
- User ratings/feedback on resources
- Completion tracking
- Personalized recommendations based on student level
- Integration with learning management systems
- Resource cost information
- Estimated completion time
- Difficulty ratings
- Language options
- Video/text format preferences

## Maintenance

### Regular Tasks:
1. **Monthly**: Review new certifications from major providers
2. **Quarterly**: Run URL validation and update broken links
3. **Semi-annually**: Review resource relevance and student feedback
4. **Annually**: Update provider list and scoring weights

### Monitoring:
- Track which resources students click most
- Monitor external link health
- Review resource coverage for all careers
- Check for outdated content

## Support

For questions or issues with the resource recommendation system:
1. Check this documentation
2. Review the database schema
3. Test with the validation script
4. Contact the system administrator

---

**Last Updated**: December 2025  
**Version**: 2.0  
**Maintained by**: Career Guidance System Team

