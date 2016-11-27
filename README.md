# DIW_Zend_Config_Multi

For compatibility with the original `Zend_Config`, this software is released
under the "New BSD" license (see LICENSE file).

Zend_Config has the ability to specify config sections which "extend" other
config sections. For example, allowing a "dev" section which is very similar to
the "production" section, aside from a few minor differences.

This use-case is solid, but there is a gap in useability when one wants to use
a separate, read-only file for "defaults", alongside another file for
"machine-specific" values, secrets, and generated data, etc. For one, the
"extends" functionality is generally processed at load-time, not at read-time,
meaning that it does not function across multiple files or formats. It also
means that reading a file containing "extends" definitions, followed immediately
by writing out the same file, will result in the "extended" data being written
to the "extending" section. It's really awful. For example:

    $ini = new Zend_Config_Ini(
      'data://text/plain,'.
      "[test]\nfoo=fooValue\nbar=barValue\n[x : test]\nfoo=xValue\n"
    );
    $writer = new Zend_Config_Writer_Ini(array('config' => $ini));
    echo $writer->render();
    # =>
    # [test]
    # foo = "fooValue"
    # bar = "barValue"
    #
    # [x : test]
    # foo = "xValue"
    # bar = "barValue"

Work-arounds can be achieved with `Zend_Config` by loading multiple files,
`->merge()`ing them together, and either keeping track of which values one
actually wants to override, or keeping objects separately for "reading" and
"writing", but the burden is usually on the programmer to perform this
bookkeeping. It really makes the whole "extends" system unuseable.

`DIW_Zend_Config_Multi` attempts to lighten this burden by encapsulating the
related bookkeeping:

 - Once a `Zend_Config` has been loaded, it can be `attach(...)`ed to a
   `Zend_Config_Multi` to act as a fallback (or `override(...)`ed to act as an
   override)

 - Methods used for "output", such as `toArray()` and the `Iterator` interface,
   output only the "dirty" values, so that these can be saved separately.

All tests can be run via `docker-compose up`, or (if you have a local php and
composer environment), `composer.phar install && vendor/phpunit/phpunit/phpunit`

Zend Framework (zf1) is a requirement for this package, but has been left out of
the "requirements" list due to peculiarities of the project it was originally
intended to support. It is expected both that this package will only be used in
support of projects which already depend on zf1, and that this oddity in the
requirements specification will be removed in a later release.
