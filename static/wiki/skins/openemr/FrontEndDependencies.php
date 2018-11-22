<?php

// TODO START: The following classes should be placed in their own files in
// ~/website-openemr/wiki/skins/openemr/dependencies

class Dependency {
	private $order;
	protected $location;
	private $appliesToWikiOnly;
	protected $lineBreakChar = "\n";

	public function	__construct( $order, $location, $appliesToWikiOnly ) {
		$this->order = $order;
		$this->location = $location;
		$this->appliesToWikiOnly = $appliesToWikiOnly;
	}

	public function getOrder() {
		return $this->order;
	}

	protected function getLocation() {
		return $this->location;
	}

	public function getAppliesToWikiOnly() {
		return $this->appliesToWikiOnly;
	}
}

class JavaScriptDependency extends Dependency {
	public function getHtmlValue() {
		return '<script type="text/javascript" src="' . $this->getLocation() . '"></script>' . $this->lineBreakChar;
	}
}

class CssDependency extends Dependency {
	public function getHtmlValue() {
		return '<link rel="stylesheet" href="' . $this->getLocation() . '" />' . $this->lineBreakChar;
	}
}

class FaviconDependency extends Dependency {
	public function getHtmlValue() {
		return '<link rel="shortcut icon" href="' . $this->getLocation() . '" />' . $this->lineBreakChar;
	}
}

class MetaDependency extends Dependency {
	private $metaData;

	public function __construct( $order, $location, $appliesToWikiOnly, $metaData ) {
		parent::__construct( $order, $location, $appliesToWikiOnly );
		$this->metaData = $metaData;
	}

	public function getMetaData() {
		return $this->metaData;
	}

	public function getHtmlValue() {
		return '<meta ' . $this->getMetaData() . ' />' . $this->lineBreakChar;
	}
}

class CacheBustedJavaScriptDependency extends JavaScriptDependency {
	protected function getLocation() {
		return CacheBusterUtil::bust( $this->location );
	}
}

class CacheBustedCssDependency extends CssDependency {
	protected function getLocation() {
		return CacheBusterUtil::bust( $this->location );
	}
}

class FrontEndDependencies {
	private $dependencies = array();

	public function	__construct() {
		$baseDir = '/wiki/skins/openemr';

		$this->dependencies[] = new CssDependency( 0, $baseDir . '/vendor/styles/bootstrap.min.css', false );
		$this->dependencies[] = new CssDependency( 1, '//maxcdn.bootstrapcdn.com/font-awesome/4.6.3/css/font-awesome.min.css', false );
		$this->dependencies[] = new CssDependency( 2, '//fonts.googleapis.com/css?family=Montserrat', false );
		$this->dependencies[] = new CssDependency( 3, '//fonts.googleapis.com/css?family=Work+Sans', false );
		$this->dependencies[] = new CacheBustedCssDependency( 4, $baseDir . '/main.css', true );
		$this->dependencies[] = new CacheBustedCssDependency( 5, $baseDir . '/openemr.css', false );
		$this->dependencies[] = new JavaScriptDependency( 6, $baseDir . '/vendor/scripts/jquery.min.js', false );
		$this->dependencies[] = new JavaScriptDependency( 7, $baseDir . '/vendor/scripts/bootstrap.min.js', false );
		$this->dependencies[] = new CacheBustedJavaScriptDependency( 8, $baseDir . '/openemr.js', false );
		$this->dependencies[] = new FaviconDependency( 9, $baseDir . '/images/favicon.ico', false );
		$this->dependencies[] = new MetaDependency( 10, null, false, 'name="HandheldFriendly" content="True"' );
		$this->dependencies[] = new MetaDependency( 11, null, false, 'name="MobileOptimized" content="320"' );
		$this->dependencies[] = new MetaDependency( 12, null, false, 'name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0"' );
        $this->dependencies[] = new MetaDependency( 13, null, true, 'property="twitter:card" content="summary_large_image"' );
        $this->dependencies[] = new MetaDependency( 14, null, true, 'property="twitter:image" content="https://www.open-emr.org/img/full_mark.png"' );
        $this->dependencies[] = new MetaDependency( 15, null, true, 'property="og:image" content="https://www.open-emr.org/img/full_mark.png"' );
        $this->dependencies[] = new MetaDependency( 16, null, true, 'property="twitter:site" content="https://twitter.com/openemr"' );
        $this->dependencies[] = new MetaDependency( 17, null, true, 'property="twitter:creator" content="@openemr"' );
        $this->dependencies[] = new MetaDependency( 18, null, true, 'property="fb:app_id" content="291996261297487"' );
	}

	public function bundleForWiki( &$pageReference ) {
		foreach ( $this->dependencies as $dependency ) {
			$pageReference->addHeadItem( $dependency->getOrder(), $dependency->getHtmlValue() );
		}
	}

	public function bundleForNonWiki() {
		$output = array();

		foreach ( $this->dependencies as $dependency ) {
			if ( !$dependency->getAppliesToWikiOnly() ) {
				array_push( $output, $dependency->getHtmlValue() );
			}
		}

		return implode( $output );
	}
}
// TODO END

// TODO START: The following class should be placed in its own files in
// ~/website-openemr/wiki/skins/openemr/utils
class CacheBusterUtil {
	public static function bust( $file ) {
		$alreadyContainsQueryString = strpos( $file, '?' );
		$valueForCacheInvalidation = time();

		if ( $alreadyContainsQueryString === false ) {
			return $file . '?v=' . $valueForCacheInvalidation;
		}

		return $file . '&v=' . $valueForCacheInvalidation;
	}
}
// TODO END
