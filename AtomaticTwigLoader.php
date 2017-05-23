<?php

class AtomaticTwigLoader extends Twig_Loader_Filesystem
{

  public function __construct($paths = array(), $rootPath = null, $separator = '-')
  {
    $this->rootPath = (null === $rootPath ? getcwd() : $rootPath) . DIRECTORY_SEPARATOR;
    if (false !== $realPath = realpath($rootPath)) {
      $this->rootPath = $realPath . DIRECTORY_SEPARATOR;
    }

    if ($paths) {
      $this->setPaths($paths);
    }

    $this->separator = $separator;
  }

  protected function resolvePathByPattern($name)
  {

    list($section, $group, $remainingSegments) = explode($this->separator, $name, 3);
    $longname = implode($this->separator, [$group, $remainingSegments]);

    switch ($section) {
      case 'svg':
        $regex = '%^' . $this->rootPath . '.*' .
                  DIRECTORY_SEPARATOR . $longname . '\.svg%i';
        break;
      default:
        $regex = '%^' . $this->rootPath . '.*' .
                  DIRECTORY_SEPARATOR . $section .
                  DIRECTORY_SEPARATOR . '([0-9][0-9]-)' . $group .
                  DIRECTORY_SEPARATOR . '.*' . $remainingSegments . '\.twig$%i';
    }

    $Directory = new RecursiveDirectoryIterator($this->rootPath);
    $Iterator = new RecursiveIteratorIterator($Directory);
    $matches = new RegexIterator($Iterator, $regex, RecursiveRegexIterator::GET_MATCH);
    $match = array_keys(iterator_to_array($matches));

    return array_shift($match);
  }

  protected function findTemplate($name, $throw = true)
  {
    $name = $this->normalizeName($name);

    if (isset($this->cache[$name])) {
      return $this->cache[$name];
    }

    if (isset($this->errorCache[$name])) {
      if (!$throw) {
        return false;
      }

      throw new Twig_Error_Loader($this->errorCache[$name]);
    }

    if (strpos($name, DIRECTORY_SEPARATOR) === false) {
      $realpath = $this->resolvePathByPattern($name);
      return $this->cache[$name] = $realpath;
    }


    $this->validateName($name);

    list($namespace, $shortname) = $this->parseName($name);


    if (!isset($this->paths[$namespace])) {

      if (!$this->isAbsolutePath($name)) {
        $path = $this->rootPath . DIRECTORY_SEPARATOR . $namespace;
      }
      if (is_file($path . DIRECTORY_SEPARATOR . $shortname)) {
        if (false !== $realpath = realpath($path . DIRECTORY_SEPARATOR . $shortname)) {
          return $this->cache[$name] = $realpath;
        }
        return $this->cache[$name] = $path . DIRECTORY_SEPARATOR . $shortname;
      }
    }

    foreach ($this->paths[$namespace] as $path) {
      if (!$this->isAbsolutePath($path)) {
        $path = $this->rootPath . DIRECTORY_SEPARATOR . $path;
      }

      if (is_file($path . DIRECTORY_SEPARATOR . $shortname)) {
        if (false !== $realpath = realpath($path . DIRECTORY_SEPARATOR . $shortname)) {
          return $this->cache[$name] = $realpath;
        }
        return $this->cache[$name] = $path . DIRECTORY_SEPARATOR . $shortname;
      }
    }

    $this->errorCache[$name] = sprintf('Unable to find template "%s" (looked into: %s).', $name, implode(', ', $this->paths[$namespace]));

    if (!$throw) {
      return false;
    }

    throw new Twig_Error_Loader($this->errorCache[$name]);
  }

  private function normalizeName($name)
  {
    return preg_replace('#/{2,}#', DIRECTORY_SEPARATOR, str_replace('\\', DIRECTORY_SEPARATOR, $name));
  }

  private function parseName($name, $default = self::MAIN_NAMESPACE)
  {
    if (isset($name[0]) && '@' == $name[0]) {
      if (false === $pos = strpos($name, DIRECTORY_SEPARATOR)) {
        throw new Twig_Error_Loader(sprintf('Malformed namespaced template name "%s" (expecting "@namespace/template_name").', $name));
      }

      $namespace = substr($name, 1, $pos - 1);
      $shortname = substr($name, $pos + 1);

      return array($namespace, $shortname);
    }

    return array($default, $name);
  }

  private function validateName($name)
  {
    if (false !== strpos($name, "\0")) {
      throw new Twig_Error_Loader('A template name cannot contain NUL bytes.');
    }

    $name = ltrim($name, DIRECTORY_SEPARATOR);
    $parts = explode(DIRECTORY_SEPARATOR, $name);
    $level = 0;
    foreach ($parts as $part) {
      if ('..' === $part) {
        --$level;
      } elseif ('.' !== $part) {
        ++$level;
      }

      if ($level < 0) {
        throw new Twig_Error_Loader(sprintf('Looks like you try to load a template outside configured directories (%s).', $name));
      }
    }
  }

  private function isAbsolutePath($file)
  {
    return strspn($file, '/\\', 0, 1)
        || (strlen($file) > 3 && ctype_alpha($file[0])
            && ':' === $file[1]
            && strspn($file, '/\\', 2, 1)
        )
        || null !== parse_url($file, PHP_URL_SCHEME);
  }
}
