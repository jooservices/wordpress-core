<?php
/**
 * Translated strings for the JavaScript runtime.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\I18n;

/**
 * The `i18n` map of the `joo-config` JSON (keys used by `t(key, fallback)` in the view modules).
 *
 * @api
 */
final class JsStrings {

	/**
	 * Hooks WordPress.
	 */
	public function hook(): void {
		add_filter( 'joo/assets/runtime_config', array( $this, 'filter' ) );
	}

	/**
	 * Adds the map to the runtime configuration.
	 *
	 * @param mixed $data Runtime configuration.
	 * @return mixed
	 */
	public function filter( $data ) {
		if ( is_array( $data ) ) {
			$data['i18n'] = array_merge( (array) ( $data['i18n'] ?? array() ), self::map() );
		}
		return $data;
	}

	/**
	 * Key => translated string.
	 *
	 * @return array<string, string>
	 */
	public static function map(): array {
		return array(
			'lazy.retry'                           => __( 'Retry', 'joo-core' ),
			'retry'                                => __( 'Retry', 'joo-core' ),
			'load_failed'                          => __( 'Could not load this page.', 'joo-core' ),
			'page_of'                              => __( 'Page {page} of {total}', 'joo-core' ),
			'present'                              => __( 'Present', 'joo-core' ),
			'toc.title'                            => __( 'On this page', 'joo-core' ),
			'did_you_mean'                         => __( 'Did you mean', 'joo-core' ),
			'terminal.close'                       => __( 'Close terminal', 'joo-core' ),
			'terminal.input'                       => __( 'Terminal command', 'joo-core' ),
			'terminal.lang_unavailable'            => __( 'This page has no translation. Use the language switcher.', 'joo-core' ),
			'terminal.blocked'                     => __( 'Blocked: not a link on this site.', 'joo-core' ),
			'terminal.banner'                      => __( 'read-only shell · nothing is sent anywhere', 'joo-core' ),
			'terminal.hint'                        => __( 'Type ', 'joo-core' ),
			'terminal.loading'                     => __( 'loading file system…', 'joo-core' ),
			'terminal.failed'                      => __( 'The terminal could not be loaded.', 'joo-core' ),
			'terminal.command.help.summary'        => __( 'list the commands', 'joo-core' ),
			'terminal.command.help.intro'          => __( 'Read-only shell. Categories are directories, posts are files. Nothing is sent anywhere.', 'joo-core' ),
			'terminal.command.help.navigation'     => __( 'Tab completes · ↑/↓ history · click names to run them', 'joo-core' ),
			'terminal.command.man.summary'         => __( 'manual entry of a command', 'joo-core' ),
			'terminal.command.man.missing_name'    => __( 'What manual page do you want?', 'joo-core' ),
			'terminal.command.man.not_found'       => __( 'No manual entry for {command}', 'joo-core' ),
			'terminal.command.whoami.summary'      => __( 'who writes here', 'joo-core' ),
			'terminal.command.hostname.summary'    => __( 'show the host name', 'joo-core' ),
			'terminal.command.id.summary'          => __( 'show the user', 'joo-core' ),
			'terminal.command.date.summary'        => __( 'current date and time', 'joo-core' ),
			'terminal.command.uname.summary'       => __( 'system name', 'joo-core' ),
			'terminal.command.echo.summary'        => __( 'print text', 'joo-core' ),
			'terminal.command.clear.summary'       => __( 'clear the screen (Ctrl+L)', 'joo-core' ),
			'terminal.command.history.summary'     => __( 'commands typed in this tab', 'joo-core' ),
			'terminal.command.exit.summary'        => __( 'close the terminal', 'joo-core' ),
			'terminal.command.logout.summary'      => __( 'alias of exit', 'joo-core' ),
			'terminal.command.which.summary'       => __( 'where a command lives', 'joo-core' ),
			'terminal.command.neofetch.summary'    => __( 'about this site', 'joo-core' ),
			'terminal.command.neofetch.os'         => __( 'OS: WordPress · block theme', 'joo-core' ),
			'terminal.command.neofetch.site'       => __( 'Site: {site}', 'joo-core' ),
			'terminal.command.neofetch.counts'     => __( 'Posts: {posts} · Directories: {directories}', 'joo-core' ),
			'terminal.command.neofetch.shell'      => __( 'Shell: joo-sh (read-only)', 'joo-core' ),
			'terminal.command.ping.summary'        => __( 'always offline by design', 'joo-core' ),
			'terminal.command.ping.offline'        => __( 'ping: connect: Network is unreachable (this shell is offline by design)', 'joo-core' ),
			'terminal.command.sudo.summary'        => __( 'not available', 'joo-core' ),
			'terminal.command.sudo.denied'         => __( '{user} is not in the sudoers file. This incident will be reported to /dev/null.', 'joo-core' ),
			'terminal.command.readonly.summary'    => __( 'refused: the file system is read-only', 'joo-core' ),
			'terminal.command.readonly.modify'     => __( "{command}: cannot modify '{path}': Read-only file system", 'joo-core' ),
			'terminal.command.readonly.editor'     => __( 'The file system is read-only. Try cat.', 'joo-core' ),
			'terminal.command.open.summary'        => __( 'go to that view on the site (post, category, tag, page)', 'joo-core' ),
			'terminal.command.open.not_found'      => __( 'xdg-open: {path}: No such file or directory → 404', 'joo-core' ),
			'terminal.command.open.no_viewer'      => __( 'no viewer for {name}; use cat', 'joo-core' ),
			'terminal.command.open.opening'        => __( 'opening {path} …', 'joo-core' ),
			'terminal.command.open.alias'          => __( 'alias of open', 'joo-core' ),
			'terminal.command.curl.summary'        => __( 'fake HTTP response for a path (no network)', 'joo-core' ),
			'terminal.command.curl.usage'          => __( "curl: try 'curl --help' for more information", 'joo-core' ),
			'terminal.command.curl.simulated'      => __( '(simulated, no network)', 'joo-core' ),
			'terminal.command.open.link'           => __( '→ open {path}', 'joo-core' ),
			'terminal.command.theme.summary'       => __( 'change the site theme', 'joo-core' ),
			'terminal.command.theme.usage'         => __( 'usage: theme dark|light', 'joo-core' ),
			'terminal.command.theme.changed'       => __( 'theme → {theme}', 'joo-core' ),
			'terminal.command.lang.summary'        => __( 'change the site language (for example lang en, lang vi)', 'joo-core' ),
			'terminal.command.lang.usage'          => __( 'usage: lang <code> (for example en or vi)', 'joo-core' ),
			'terminal.command.lang.changed'        => __( 'lang → {language}', 'joo-core' ),
			'terminal.command.file.missing'        => __( "{command}: cannot access '{path}': No such file or directory", 'joo-core' ),
			'terminal.file.category'               => __( 'category: ', 'joo-core' ),
			'terminal.file.date'                   => __( 'date: ', 'joo-core' ),
			'terminal.file.read'                   => __( 'read: ', 'joo-core' ),
			'terminal.file.tags'                   => __( 'tags: ', 'joo-core' ),
			'terminal.command.ls.summary'          => __( 'list directory (categories) and posts', 'joo-core' ),
			'terminal.command.empty'               => __( '(empty)', 'joo-core' ),
			'terminal.command.total'               => __( 'total {count}', 'joo-core' ),
			'terminal.command.cd.summary'          => __( 'change directory (cd .., cd ~, cd -)', 'joo-core' ),
			'terminal.command.cd.missing'          => __( 'bash: cd: {path}: No such file or directory', 'joo-core' ),
			'terminal.command.cd.not_directory'    => __( 'bash: cd: {path}: Not a directory', 'joo-core' ),
			'terminal.command.pwd.summary'         => __( 'print working directory', 'joo-core' ),
			'terminal.command.cat.summary'         => __( 'show a post summary or page', 'joo-core' ),
			'terminal.command.cat.missing'         => __( 'cat: missing file operand', 'joo-core' ),
			'terminal.command.cat.not_found'       => __( 'cat: {path}: No such file or directory', 'joo-core' ),
			'terminal.command.cat.is_directory'    => __( 'cat: {path}: Is a directory', 'joo-core' ),
			'terminal.command.head.summary'        => __( 'first lines of a file', 'joo-core' ),
			'terminal.command.head.missing'        => __( 'head: missing file operand', 'joo-core' ),
			'terminal.command.head.not_found'      => __( "head: cannot open '{path}' for reading: No such file or directory", 'joo-core' ),
			'terminal.command.head.is_directory'   => __( "head: error reading '{path}': Is a directory", 'joo-core' ),
			'terminal.command.tree.summary'        => __( 'directory tree', 'joo-core' ),
			'terminal.command.tree.not_found'      => __( '{path} [error opening dir]', 'joo-core' ),
			'terminal.command.tree.count'          => __( '{directories} directories, {files} files', 'joo-core' ),
			'terminal.command.find.summary'        => __( 'find files and directories by name', 'joo-core' ),
			'terminal.command.find.not_found'      => __( "find: '{path}': No such file or directory", 'joo-core' ),
			'terminal.command.find.no_matches'     => __( '(no matches)', 'joo-core' ),
			'terminal.command.grep.summary'        => __( 'search titles, excerpts and tags', 'joo-core' ),
			'terminal.command.grep.usage'          => __( 'Usage: grep [OPTION]... PATTERN [FILE]...', 'joo-core' ),
			'terminal.command.grep.not_found'      => __( 'grep: {path}: No such file or directory', 'joo-core' ),
			'terminal.command.grep.no_match'       => __( '(no match)', 'joo-core' ),
			'terminal.command.grep.case_sensitive' => __( '(no match. grep is case-sensitive, try -i)', 'joo-core' ),
		);
	}
}
