# fredbradley/laravel-version-health-check

Spatie Laravel Health Checks that check your Laravel Application's and PHP's version against the latest available release.

## Instructions

First, ensure that [Spatie Laravel Health](https://spatie.be/docs/laravel-health/v1/introduction) is set up and working as expected on your instance. [Documentation can be found here](https://spatie.be/docs/laravel-health/v1/introduction).

Then install this package:
```
composer require fredbradley/laravel-version-health-check
```

This registers two checks, "**Laravel Version**" and "**PHP Version**". If you have health checks already using those names, you will have a conflict. Otherwise, it works out of the bag.

As per the other Spatie Laravel Health documentation.

## Contribution
You're very welcome to submit PRs. 

### Suggested features you could work on
 - Ability to customise the Health Check Name.
 - Change how long the cache stores the results from the GitHub API.
 - Add a Github auth token so we're less likely to be rate limited. *OR* change the check to use the packagist.org api instead.
