# Property Scout
This is a plugin for wordpress that integrates into various real estate APIs to search and pull listing data. So far, the only API provider is Zillow. It uses vinceg's [zillow client](https://github.com/VinceG/zillow) library. You will need a Zillow ZWSID to use.

### Example
```php
$search = trim(@$_GET['search']);
$results = apply_filters('prop_scout_search', array(), $search);

foreach($results as $result) {
  $addr = trim(@$result['address']);
  echo $addr;
}
```

