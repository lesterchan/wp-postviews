/**
 * The most/least viewed listings: the six template tags, the tokens the Most
 * Viewed Template accepts, the site-wide total, and the widget that wraps the
 * same query in a sidebar.
 *
 * Everything is asserted on the front page rather than on a single post, and on
 * the administrator's own session rather than a guest's, for one reason: the
 * shipped "Count Views From: Guests Only" means neither request is ever
 * counted, so no assertion about an order can be disturbed by the very visit
 * that made it.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	addViewsWidget,
	clearAllViews,
	installProbe,
	probeTerm,
	removeProbe,
	removeViewsWidget,
	resetOptions,
	setOptions,
	setThumbnail,
	setViews,
	uniqueTitle,
	views,
} = require( './helpers.js' );

test.describe( 'Most and least viewed listings', () => {
	let popular;
	let middling;
	let unloved;
	let staticPage;
	let categoryId;
	let tagId;

	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
		await requestUtils.deleteAllPages();
		installProbe();

		const category = await requestUtils.rest( {
			method: 'POST',
			path: '/wp/v2/categories',
			data: { name: uniqueTitle( 'Listing category' ) },
		} );
		categoryId = category.id;

		const tag = await requestUtils.rest( {
			method: 'POST',
			path: '/wp/v2/tags',
			data: { name: uniqueTitle( 'Listing tag' ) },
		} );
		tagId = tag.id;

		popular = await requestUtils.createPost( {
			title: 'Popular post with a very long title indeed',
			content: 'Widely read.',
			status: 'publish',
			categories: [ categoryId ],
			tags: [ tagId ],
		} );
		middling = await requestUtils.createPost( {
			title: 'Middling post',
			content: 'Read sometimes.',
			status: 'publish',
		} );
		unloved = await requestUtils.createPost( {
			title: 'Unloved post',
			content: 'Barely read.',
			status: 'publish',
			categories: [ categoryId ],
		} );
		staticPage = await requestUtils.createPage( {
			title: 'Listed page',
			content: 'A page in the listings.',
			status: 'publish',
		} );

		probeTerm( 'cat', categoryId );
		probeTerm( 'tag', tagId );
	} );

	test.afterAll( async () => {
		removeProbe();
		removeViewsWidget();
		resetOptions();
	} );

	test.beforeEach( async () => {
		resetOptions();
		clearAllViews();

		// Widely spaced on purpose: the counts decide the order, and gaps this
		// large cannot be closed by a stray view arriving mid-test.
		setViews( popular.id, 300 );
		setViews( middling.id, 200 );
		setViews( unloved.id, 100 );
		setViews( staticPage.id, 250 );
	} );

	test.afterEach( async () => {
		resetOptions();
	} );

	test( 'the fixture really is four items with four different stored counts', () => {
		// The whole file is about ordering, so a fixture where two counts had
		// silently become equal would make every assertion below a coin toss.
		expect( [
			views( popular.id ),
			views( staticPage.id ),
			views( middling.id ),
			views( unloved.id ),
		] ).toEqual( [ 300, 250, 200, 100 ] );
	} );

	test( 'get_most_viewed lists everything in descending order of views', async ( { page } ) => {
		await page.goto( '/' );

		await expect( page.locator( '#pv-most li a' ) ).toHaveText( [
			'Popular post with a very long title indeed',
			'Listed page',
			'Middling post',
			'Unloved post',
		] );
	} );

	test( 'get_least_viewed lists the same items in ascending order', async ( { page } ) => {
		await page.goto( '/' );

		await expect( page.locator( '#pv-least li a' ) ).toHaveText( [
			'Unloved post',
			'Middling post',
			'Listed page',
			'Popular post with a very long title indeed',
		] );
	} );

	test( 'a post type argument scopes the listing to that type', async ( { page } ) => {
		await page.goto( '/' );

		// The page outranks two of the posts, so its absence here is the filter
		// working rather than the ordering happening to hide it.
		await expect( page.locator( '#pv-most-posts li a' ) ).toHaveText( [
			'Popular post with a very long title indeed',
			'Middling post',
			'Unloved post',
		] );
	} );

	test( 'a post with no stored count is left out of the listing entirely', async ( { page } ) => {
		// Deleting the meta row is what a post that has never been viewed looks
		// like; the listing's meta_key filter is the only thing keeping it out,
		// and without it every unread post would be listed at zero.
		clearAllViews();
		setViews( popular.id, 5 );

		await page.goto( '/' );

		await expect( page.locator( '#pv-most li a' ) ).toHaveText( [
			'Popular post with a very long title indeed',
		] );
	} );

	test( 'the listing says N/A when nothing has been viewed at all', async ( { page } ) => {
		clearAllViews();

		await page.goto( '/' );

		await expect( page.locator( '#pv-most li' ) ).toHaveText( [ 'N/A' ] );
	} );

	test( 'the by-category listings are scoped to the category, in both directions', async ( {
		page,
	} ) => {
		await page.goto( '/' );

		// Only two of the four fixtures are in the category, and the middling
		// post -- which sits between them by count -- is not, so a listing that
		// ignored the scope would show three entries.
		await expect( page.locator( '#pv-most-cat li a' ) ).toHaveText( [
			'Popular post with a very long title indeed',
			'Unloved post',
		] );
		await expect( page.locator( '#pv-least-cat li a' ) ).toHaveText( [
			'Unloved post',
			'Popular post with a very long title indeed',
		] );
	} );

	test( 'the by-tag listings are scoped to the tag, in both directions', async ( { page } ) => {
		await page.goto( '/' );

		await expect( page.locator( '#pv-most-tag li a' ) ).toHaveText( [
			'Popular post with a very long title indeed',
		] );
		await expect( page.locator( '#pv-least-tag li a' ) ).toHaveText( [
			'Popular post with a very long title indeed',
		] );
	} );

	test( 'the characters argument truncates a long title and leaves a short one alone', async ( {
		page,
	} ) => {
		await page.goto( '/' );

		// Twelve characters, from the probe. "Listed page" is shorter than that
		// and comes back untouched, which is the half that matters: the
		// ellipsis is only added when something was actually cut.
		await expect( page.locator( '#pv-most-chars li a' ).first() ).toHaveText( 'Popular post...' );
		await expect( page.locator( '#pv-most-chars li a' ).nth( 1 ) ).toHaveText( 'Listed page' );
	} );

	test( 'get_totalviews adds up every stored count on the site', async ( { page } ) => {
		await page.goto( '/' );

		await expect( page.locator( '#pv-total' ) ).toHaveText( '850' );
	} );

	test( 'the Most Viewed Template substitutes every token it advertises', async ( { page } ) => {
		// All twelve in one template. Splitting them into a test each would say
		// nothing extra -- they are one str_replace over one array -- but
		// leaving any of them out would let a token quietly stop resolving and
		// print itself to visitors.
		setThumbnail( popular.id );
		setOptions( {
			most_viewed_template:
				'<li class="pv-entry" data-views="%VIEW_COUNT%" data-rounded="%VIEW_COUNT_ROUNDED%" data-url="%POST_URL%" data-author="%POST_AUTHOR%" data-category="%POST_CATEGORY_ID%" data-date="%POST_DATE%" data-time="%POST_TIME%" data-thumb-url="%POST_THUMBNAIL_URL%">%POST_TITLE% | %POST_EXCERPT% | %POST_CONTENT% | %POST_THUMBNAIL%</li>',
		} );

		await page.goto( '/' );

		const entry = page.locator( '#pv-most li.pv-entry' ).first();

		await expect( entry ).toContainText( 'Popular post with a very long title indeed' );
		// The excerpt is generated from the content, so both tokens carry it.
		await expect( entry ).toContainText( 'Widely read.' );
		await expect( entry ).toHaveAttribute( 'data-views', '300' );
		await expect( entry ).toHaveAttribute( 'data-rounded', '300' );
		await expect( entry ).toHaveAttribute( 'data-url', popular.link );
		await expect( entry ).toHaveAttribute( 'data-author', 'admin' );
		await expect( entry ).toHaveAttribute( 'data-category', String( categoryId ) );
		await expect( entry ).toHaveAttribute( 'data-thumb-url', /\.png$/ );
		await expect( entry.locator( 'img' ) ).toHaveCount( 1 );

		// Whatever the site's date and time formats render to, neither is the
		// raw token.
		await expect( entry ).not.toHaveAttribute( 'data-date', '%POST_DATE%' );
		await expect( entry ).not.toHaveAttribute( 'data-time', '%POST_TIME%' );
	} );

	test( 'the thumbnail tokens render empty for a post that has no featured image', async ( {
		page,
	} ) => {
		// The other side of the token above. get_the_post_thumbnail_url()
		// returns false when there is no image, and a token that printed
		// "false" -- or the literal token -- into an href would be visible on
		// every site that does not use featured images.
		setOptions( {
			most_viewed_template:
				'<li class="pv-entry" data-thumb-url="%POST_THUMBNAIL_URL%">%POST_THUMBNAIL%%POST_TITLE%</li>',
		} );

		await page.goto( '/' );

		const entry = page.locator( '#pv-most li.pv-entry' ).nth( 2 );
		await expect( entry ).toHaveText( 'Middling post' );
		await expect( entry ).toHaveAttribute( 'data-thumb-url', '' );
		await expect( entry.locator( 'img' ) ).toHaveCount( 0 );
	} );

	test( 'the widget renders its title and the most viewed list in the sidebar', async ( {
		page,
	} ) => {
		addViewsWidget( { title: 'Top Reads', type: 'most_viewed', limit: 2, chars: 0 } );

		await page.goto( '/' );

		const widget = page.locator( '#views-9' );
		await expect( widget ).toContainText( 'Top Reads' );
		await expect( widget.locator( 'li a' ) ).toHaveText( [
			'Popular post with a very long title indeed',
			'Listed page',
		] );
	} );

	test( 'the widget honours its statistics type and its record limit', async ( { page } ) => {
		addViewsWidget( { title: 'Least Read', type: 'least_viewed', limit: 1, chars: 0 } );

		await page.goto( '/' );

		// One entry because the limit says one, and the least viewed one
		// because the type says so -- the pair rules out a widget that renders
		// a fixed listing whatever it was configured with.
		await expect( page.locator( '#views-9 li a' ) ).toHaveText( [ 'Unloved post' ] );
	} );

	test( 'the widget scoped to a category lists only that category, in both directions', async ( {
		page,
	} ) => {
		addViewsWidget( {
			title: 'By Category',
			type: 'most_viewed_category',
			limit: 5,
			chars: 0,
			cat_ids: String( categoryId ),
		} );

		await page.goto( '/' );

		await expect( page.locator( '#views-9 li a' ) ).toHaveText( [
			'Popular post with a very long title indeed',
			'Unloved post',
		] );

		addViewsWidget( {
			title: 'By Category',
			type: 'least_viewed_category',
			limit: 5,
			chars: 0,
			cat_ids: String( categoryId ),
		} );

		await page.goto( '/' );

		await expect( page.locator( '#views-9 li a' ) ).toHaveText( [
			'Unloved post',
			'Popular post with a very long title indeed',
		] );
	} );

	test( 'the widget honours its post type and its title length', async ( { page } ) => {
		addViewsWidget( { title: 'Posts Only', type: 'most_viewed', mode: 'post', limit: 5, chars: 12 } );

		await page.goto( '/' );

		// The page is missing because of the mode, and the long title is cut
		// because of chars -- the two widget settings the listing tests reach
		// only through the template tags.
		await expect( page.locator( '#views-9 li a' ) ).toHaveText( [
			'Popular post...',
			'Middling pos...',
			'Unloved post',
		] );
	} );

	test( 'a widget with an empty title renders the list and no heading', async ( { page } ) => {
		addViewsWidget( { title: '', type: 'most_viewed', limit: 1, chars: 0 } );

		await page.goto( '/' );

		// The widget skips before_title/after_title entirely when the title is
		// empty, rather than emitting an empty heading a theme would still draw
		// a rule under.
		await expect( page.locator( '#views-9 .widget-title' ) ).toHaveCount( 0 );
		await expect( page.locator( '#views-9 li a' ) ).toHaveText( [
			'Popular post with a very long title indeed',
		] );
	} );
} );
