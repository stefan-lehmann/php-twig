<?php

class Twig_Loader_Atomatic extends Twig_Loader_Filesystem
{


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

    $this->validateName($name);

    list($namespace, $shortname) = $this->parseName($name);

    if (!isset($this->paths[$namespace])) {
      $this->errorCache[$name] = sprintf('There are no registered paths for namespace "%s".', $namespace);

      if (!$throw) {
        return false;
      }

      throw new Twig_Error_Loader($this->errorCache[$name]);
    }

    foreach ($this->paths[$namespace] as $path) {
      if (!$this->isAbsolutePath($path)) {
        $path = $this->rootPath . '/' . $path;
      }

      if (is_file($path . '/' . $shortname)) {
        if (false !== $realpath = realpath($path . '/' . $shortname)) {
          return $this->cache[$name] = $realpath;
        }

        return $this->cache[$name] = $path . '/' . $shortname;
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
    return preg_replace('#/{2,}#', '/', str_replace('\\', '/', $name));
  }

  private function parseName($name, $default = self::MAIN_NAMESPACE)
  {
    if (isset($name[0]) && '@' == $name[0]) {
      if (false === $pos = strpos($name, '/')) {
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

    $name = ltrim($name, '/');
    $parts = explode('/', $name);
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
