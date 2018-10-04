# laravel-topology

A Laravel package for [`password-topology-check`][]

## What is it?

The `password-topology-check` package provides a simple utility for converting
passwords to their topologies. It also offers the ability to compare the result
against the top 100 most common topologies (also known as the [PathWell
Topologies][]), and reject any new passwords that match. The idea is to reduce
your sites' attack planes, by making passwords harder to guess.

So what is a password topology? Well, it goes back to the "character classes"
used on many sites to improve security. There are four of those, in general:
upper case, lower case, digits, and symbols. The topology of a password, then,
is the pattern of its character classes. `P@5s`, for example, has the topology
`upper, symbol, digit, lower`, or `usdl` for short, while `w0®D` is `ldsu`.
Ensuring your users don't re-use a PathWell Topology ensures hackers need to
work through all of those before they start reaching ones they can break. Many
will move on before they get that far.

Of course, even that isn't enough, just as requiring all four character classes
isn't. The real benefit isn't from blocking the most common, though that's a
good start. Instead, the true benefit comes from the other things we can do with
it. On a per-user level, it's easy to convert both a new and an old password to
their repsective topologies, then calculate the Levenshtein Distance between
them. If the topology hasn't changed by a high enough factor (the default, here,
is 2), the password hasn't changed enough, either. Then, as an added layer, the
concept of wear-leveling can be added in to prevent users from re-using a
topology already in use by other users (or their previous passwords).
Wear-leveling your password topologies requires a second data store of some
kind, to prevent attackers from getting too much information about which
topologies are in use, but spreads your passwords out more, making them harder
to crack, and reducing the chances that cracking one will help reveal several
others.

## OK, so what is _this_?

This package hooks the [`password-topology-check`][] package into Laravel
projects. It registers two new validation rules you can use to compare
topologies and provide wear-leveling. The configuration file provides some
options for how each of these features should be handled. Other than that, it
tries to stay out of the way.

### Usage

If your Laravel version is older than 5.5, you'll need to add the service
provider to your `config/app.php` manually:

```php
    'providers' => [
        // ...
        DanHunsaker\PasswordTopology\TopologyServiceProvider::class,
    ],
```

Unless the defaults are fine for your app, you'll want to publish the
configuration and language files:

```bash
php artisan vendor:publish --provider DanHunsaker\\PasswordTopology\\TopologyServiceProvider
```

#### Validations: `topology`

Out of the box, you'll have access to two new validation rules. The first is
`topology`, and there are two ways to use it. The first is checking the input
against the internal forbidden topologies list:

```php
$v = Validator::make(
    ['password' => '12345QWERTqwert@'],
    ['password' => 'topology']
);
$v->passes();
```

The internal list can be modified directly at startup, and also automatically as
passwords are created/updated – that is, they can also be wear-leveled. See the
[configuration](#configuration) section, below, for more on how to set that up.

#### Validations: `topology:{list}`

The second way to use the `topology` rule is checking it against a hard-coded
topologies list:

```php
$v = Validator::make(
    ['ssn' => '555-55-5555'],
    ['ssn' => 'topology:dddsddsdddd,ddddddddd']
);
$v->passes();
```

Or perhaps:

```php
// Don't allow US phone numbers without area codes
$v = Validator::make(
    ['phone' => '555-555-5555'],
    ['phone' => 'topology:!ddddddd,!dddsdddd']
);
$v->passes();
```

Topologies with a leading `!` are forbidden, while bare topologies are allowed.
So the examples above would allow `555-55-5555` and `555555555`, but not
`555-555-555`; and `555-555-5555` and `(555) 555-5555` but not `555-5555` or
`5555555`. If any topologies are explicitly allowed (that is, if the list
includes a bare topology), then only the allowed topologies will pass
validation.

#### Validations: `topo-dist:{field}`

The second new validation rule is `topo-dist`, and checks that a new password's
topology is at least the configured Levenshtein Distance from the old one's:

```php
$v = Validator::make(
    ['current' => 'QWERT12345qwert!', 'password' => '12345QWERTqwert@'],
    ['password' => 'topo-dist:current']
);
$v->passes();
```

> NOTE: Password resets can't take advantage of this functionality, as the
> previous password won't be available to compare against.

#### Topology Usage Tracking

This package also provides support for auditing your topology usage, and
wear-leveling your topologies, but it takes a bit of extra setup to use. The
first step is to update your `ResetPasswordController` to use the
`ResetsPasswords` trait from this package instead of Laravel's. This is super
simple, though – just change:

```php
use Illuminate\Foundation\Auth\ResetsPasswords;
```

to:

```php
use DanHunsaker\PasswordTopology\ResetsPasswords;
```

The second step is to update your `RegisterController` to use the
`updateTopologyUsage` method of this package's `TracksTopologyUsage` trait when
creating new users:

```php
use DanHunsaker\PasswordTopology\TracksTopologyUsage;

class RegisterController extends Controller
{
    use RegistersUsers, TracksTopologyUsage;

    // ...

    protected function create(array $data)
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $this->updateTopologyUsage($data['password']);

        return $user;
    }
}
```

Then just do the same thing you did to the `RegisterController` to any/all
controllers you use to _update_ users, and you're set to collect the topology
usage data you need, either for auditing or for wear leveling (or potentially
both?).

### Configuration

The configuration file has a number of options for enabling and disabling
additional features. Each is described below, along with their default values so
you can decide whether to publish the configuration file or not.

* `unicode` (`true`)

  When set to `true`, all topology conversions will be done in Unicode mode,
  with full support for all the various scripts Unicode supports. **It is
  _strongly_ recommended to leave this set to `true` to properly support any
  users you may have with non-English keyboards and languages.** The non-Unicode
  mode doesn't properly support even _European_ language characters that aren't
  in the English character set (that is, in the ASCII character ranges). It can
  be set to `false` mostly for compatibility with applications/servers that
  don't support Unicode themselves, or in other legacy use cases where non-ASCII
  letters and numbers should be treated as symbols.

* `min_lev_dist` (`2`)

  Configures how much the password topology must change when being updated. This
  value is used by the `topo-dist` validator to determine how similar two
  passwords can be without failing the check. The default requires two or more
  changes to the topology; a value of `0` doesn't require any changes at all.

* `audit_store` (`null`)

  The database connection to use for tracking topology usage information. This
  should _not_ be the same connection where your passwords are stored, as it
  provides hints to would-be hackers on which password topologies to brute-force
  against. When set to `null` (the default), no usage data is stored at all.

* `max_topo_use` (`1`)

  When a valid database connection is given in `audit_store`, any topologies
  with a usage at or above this level will automatically be added to the
  forbidden list at startup, preventing them from being used by other passwords
  on the same site. Since the usage data only tracks that a password is _set_
  for each topology, this covers the entire history of your site's passwords,
  from the moment the `audit_store` is created. It is recommended to increase
  this value rather than destroy the `audit_store` data, as that will provide
  better guarantees over time.

  > When enabled, someone attempting to break into your site will only be able
  > to crack `max_topo_use` passwords per topology they try.

  Setting this to `0` will allow you to collect usage data without enforcing
  _any_ topology usage limits, but remember you lose the wear-leveling effect,
  and all restrictions on maximum cracks per topology.

* `forbidden` / `allowed` (`[]` / `[]`)

  Adds or removes topologies, respectively, from the forbidden list at startup.
  The `allowed` topologies are processed first, ensuring that `forbidden`
  topologies have precedence. The [PathWell Topologies][] are already in the
  forbidden list before startup, so they don't need to be listed, here.

  The topology values use the following components:

  - `u`: uppercase (`A-Z`, or any upper/title case letters in Unicode mode)
  - `l`: lowercase (`a-z`, or any letters not in upper/title case in Unicode mode)
  - `d`: digit (`0-9`, or any Unicode number)
  - `s`: symbol (anything else, in either mode)

  See [What Is It?](#what-is-it), above, for more details.

[PathWell Topologies]: https://blog.korelogic.com/blog/2014/04/04/pathwell_topologies
[`password-topology-check`]: https://github.com/danhunsaker/password-topology-check
