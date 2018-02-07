<?php

class HTTPSOptInHooks {
	public static function onGetPreferences( User $user, array &$preferences ): bool {
		global $wgAllowHTTPS;
		if ( !empty( $wgAllowHTTPS ) && $user->isAllowed( 'https-opt-in' ) ) {
			$preferences['https-opt-in'] = [
				'type' => 'toggle',
				'label-message' => 'https-opt-in-toggle',
				'section' => 'under-the-hood/advanced-displayv2',
			];
		}
		return true;
	}

	public static function onMercuryWikiVariables( array &$wikiVariables ): bool {
		$basePath = $wikiVariables['basePath'];
		$user = RequestContext::getMain()->getUser();
		if ( startsWith( $basePath, 'http://' ) && self::userAllowedHTTPS( $user ) ) {
			$wikiVariables['basePath'] = preg_replace( '/^http:\/\//', 'https://', $basePath );
		} elseif ( startsWith( $basePath, 'https://' ) &&
			!self::userAllowedHTTPS( $user ) &&
			!self::disableHTTPSDowngrade()
		) {
			$wikiVariables['basePath'] = preg_replace( '/^https:\/\//', 'http://', $basePath );
		}
		return true;
	}

	/**
	 * Handle redirecting users who have opted in to HTTPS, and those
	 * who haven't back to HTTP if necessary.
	 *
	 * @param  Title      $title
	 * @param             $unused
	 * @param  OutputPage $output
	 * @param  User       $user
	 * @param  WebRequest $request
	 * @param  MediaWiki  $mediawiki
	 * @return bool
	 */
	public static function onBeforeInitialize( Title $title, $unused, OutputPage $output,
		User $user, WebRequest $request, MediaWiki $mediawiki
	): bool {
		if ( WebRequest::detectProtocol() === 'http' &&
			self::userAllowedHTTPS( $user )
		) {
			$output->redirect( preg_replace( '/^http:\/\//', 'https://', $request->getFullRequestURL() ) );
		} elseif ( WebRequest::detectProtocol() === 'https' &&
			!self::userAllowedHTTPS( $user ) &&
			!self::disableHTTPSDowngrade()
		) {
			$output->redirect( preg_replace( '/^https:\/\//', 'http://', $request->getFullRequestURL() ) );
		}
		return true;
	}

	private static function userAllowedHTTPS( User $user ): bool {
		global $wgAllowHTTPS;
		return !empty( $wgAllowHTTPS ) &&
			$user->isAllowed( 'https-opt-in' ) &&
			$user->getGlobalPreference( 'https-opt-in', false );
	}

	private static function disableHTTPSDowngrade(): bool {
		global $wgDevelEnvironment, $wgStagingEnvironment, $wgDisableHTTPSDowngrade;
		return ( !empty( $wgDevelEnvironment ) || !empty( $wgStagingEnvironment ) ) &&
			!empty( $wgDisableHTTPSDowngrade );
	}
}