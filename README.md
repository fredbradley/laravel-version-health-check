# fredbradley/laravel-version-health-check

A Spatie Laravel Health Check that checks your Laravel Application version against the latest version release.

## Instructions

First, ensure that [Spatie Laravel Health](https://spatie.be/docs/laravel-health/v1/introduction) is set up and working as expected on your instance. [Documentation can be found here](https://spatie.be/docs/laravel-health/v1/introduction).

Then install this package:
```
composer require fredbradley/laravel-version-health-check
```

The check is called "Laravel Version." If you have a health check already using that name, you will have a conflict. Otherwise, it works out of the bag.

As per the other Spatie Laravel Health documentation.

## Contribution
You're very welcome to submit PRs. 
## Suggested features you could work on
 - Ability to customise the Health Check Name
 - Change how long the cache stores the results from the GitHub API.
 - Change the check so that perhaps a warning is given if the version doesn't match the same `minor` version, but a danger is given if it doesn't match the `major` version
