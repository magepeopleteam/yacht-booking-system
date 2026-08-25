import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { api } from '../api/client';
import { navigate } from '../router';
import { toast } from '../components/Toast';

const VIEW_STORAGE_KEY = 'ybs_yachts_view';

function getStoredView() {
	try {
		const stored = window.localStorage.getItem( VIEW_STORAGE_KEY );
		return 'grid' === stored ? 'grid' : 'list';
	} catch ( e ) {
		return 'list';
	}
}

function formatPrice( amount, currency ) {
	if ( ! amount ) {
		return '';
	}

	const rounded = Number( amount ) % 1 === 0 ? Number( amount ).toFixed( 0 ) : Number( amount ).toFixed( 2 );
	return `${ currency }${ rounded }`;
}

function StatusBadge( { status } ) {
	return (
		<span className={ 'ybs-badge status-' + ( 'publish' === status ? 'paid' : 'pending' ) }>
			{ 'publish' === status ? __( 'Published', 'yacht-booking-system' ) : __( 'Draft', 'yacht-booking-system' ) }
		</span>
	);
}

function YachtCardSkeleton() {
	return (
		<div className="ybs-yacht-card ybs-yacht-card--skeleton">
			<div className="ybs-skeleton ybs-yacht-card__media" />
			<div className="ybs-yacht-card__body">
				<div className="ybs-skeleton" style={ { height: 16, width: '70%', marginBottom: 10 } } />
				<div className="ybs-skeleton" style={ { height: 12, width: '45%', marginBottom: 14 } } />
				<div className="ybs-skeleton" style={ { height: 12, width: '90%' } } />
			</div>
		</div>
	);
}

function YachtRowSkeleton() {
	return (
		<div className="ybs-yacht-row ybs-yacht-row--skeleton">
			<div className="ybs-skeleton ybs-yacht-row__thumb" />
			<div className="ybs-yacht-row__main">
				<div className="ybs-skeleton" style={ { height: 14, width: '35%', marginBottom: 8 } } />
				<div className="ybs-skeleton" style={ { height: 11, width: '20%' } } />
			</div>
		</div>
	);
}

export default function YachtsList() {
	const [ items, setItems ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ search, setSearch ] = useState( '' );
	const [ status, setStatus ] = useState( 'any' );
	const [ currency, setCurrency ] = useState( '$' );
	const [ view, setView ] = useState( getStoredView );
	const [ dummySeeded, setDummySeeded ] = useState( true );
	const [ importing, setImporting ] = useState( false );

	const load = ( query ) => {
		api.get( '/yachts', { per_page: 50, search: query || undefined } )
			.then( ( res ) => {
				setItems( res.items );
				setDummySeeded( !! res.dummy_seeded );
			} )
			.catch( ( err ) => setError( err.message ) );
	};

	const importDummyYachts = () => {
		setImporting( true );

		api.post( '/yachts/dummy-import' )
			.then( ( res ) => {
				setImporting( false );
				setDummySeeded( true );
				toast( sprintf( __( '%d sample yachts imported.', 'yacht-booking-system' ), res.imported ) );
				load( search );
			} )
			.catch( ( err ) => {
				setImporting( false );
				toast( err.message, 'error' );
			} );
	};

	useEffect( () => {
		api.get( '/settings' ).then( ( s ) => setCurrency( s.currency_symbol || '$' ) ).catch( () => {} );
	}, [] );

	useEffect( () => {
		const delay = search ? 350 : 0;
		const id = setTimeout( () => load( search ), delay );
		return () => clearTimeout( id );
	}, [ search ] );

	const changeView = ( next ) => {
		setView( next );
		try {
			window.localStorage.setItem( VIEW_STORAGE_KEY, next );
		} catch ( e ) {
			// Ignore - the view preference just won't persist across reloads.
		}
	};

	const remove = ( id, title ) => {
		if ( ! window.confirm( sprintf( __( 'Delete "%s"? This cannot be undone.', 'yacht-booking-system' ), title ) ) ) {
			return;
		}

		api.del( `/yachts/${ id }` ).then( () => load( search ) );
	};

	const filtered = useMemo( () => {
		if ( ! items ) {
			return null;
		}

		return 'any' === status ? items : items.filter( ( yacht ) => yacht.status === status );
	}, [ items, status ] );

	const clearFilters = () => {
		setSearch( '' );
		setStatus( 'any' );
	};

	return (
		<div className="ybs-yachts-list">
			<div className="ybs-page-header">
				<div>
					<h2>{ __( 'Yachts', 'yacht-booking-system' ) }</h2>
					<p>{ __( 'Manage your fleet.', 'yacht-booking-system' ) }</p>
				</div>
				<div className="ybs-page-header__actions">
					{ ! dummySeeded && (
						<button className="ybs-btn" onClick={ importDummyYachts } disabled={ importing }>
							<span className="dashicons dashicons-download" />
							{ importing ? __( 'Importing…', 'yacht-booking-system' ) : __( 'Import Dummy Data', 'yacht-booking-system' ) }
						</button>
					) }
					<button className="ybs-btn is-primary" onClick={ () => navigate( 'yachts/new' ) }>
						<span className="dashicons dashicons-plus-alt2" />
						{ __( 'Add New Yacht', 'yacht-booking-system' ) }
					</button>
				</div>
			</div>

			{ error && <div className="ybs-notice is-error">{ error }</div> }

			{ items && items.length > 0 && (
				<div className="ybs-yachts-toolbar">
					<div className="ybs-yachts-search">
						<span className="dashicons dashicons-search" />
						<input
							type="text"
							value={ search }
							onChange={ ( e ) => setSearch( e.target.value ) }
							placeholder={ __( 'Search yachts by name…', 'yacht-booking-system' ) }
						/>
					</div>

					<div className="ybs-yachts-filters">
						{ [ 'any', 'publish', 'draft' ].map( ( value ) => (
							<button
								key={ value }
								type="button"
								className={ 'ybs-chip-filter' + ( status === value ? ' is-active' : '' ) }
								onClick={ () => setStatus( value ) }
							>
								{ value === 'any' && __( 'All', 'yacht-booking-system' ) }
								{ value === 'publish' && __( 'Published', 'yacht-booking-system' ) }
								{ value === 'draft' && __( 'Drafts', 'yacht-booking-system' ) }
							</button>
						) ) }
					</div>

					{ filtered && (
						<span className="ybs-yachts-count">
							{ sprintf( __( '%d yacht(s)', 'yacht-booking-system' ), filtered.length ) }
						</span>
					) }

					<div className="ybs-view-switch">
						<button
							type="button"
							className={ 'ybs-view-switch__btn' + ( 'list' === view ? ' is-active' : '' ) }
							onClick={ () => changeView( 'list' ) }
							aria-label={ __( 'List view', 'yacht-booking-system' ) }
							title={ __( 'List view', 'yacht-booking-system' ) }
						>
							<span className="dashicons dashicons-list-view" />
						</button>
						<button
							type="button"
							className={ 'ybs-view-switch__btn' + ( 'grid' === view ? ' is-active' : '' ) }
							onClick={ () => changeView( 'grid' ) }
							aria-label={ __( 'Grid view', 'yacht-booking-system' ) }
							title={ __( 'Grid view', 'yacht-booking-system' ) }
						>
							<span className="dashicons dashicons-grid-view" />
						</button>
					</div>
				</div>
			) }

			{ ! items && ! error && (
				'grid' === view ? (
					<div className="ybs-yacht-grid">
						{ Array.from( { length: 6 } ).map( ( _, i ) => <YachtCardSkeleton key={ i } /> ) }
					</div>
				) : (
					<div className="ybs-yacht-list">
						{ Array.from( { length: 5 } ).map( ( _, i ) => <YachtRowSkeleton key={ i } /> ) }
					</div>
				)
			) }

			{ items && items.length === 0 && (
				<div className="ybs-empty-state ybs-empty-state--hero">
					<div className="ybs-empty-state__icon">
						<span className="dashicons dashicons-palmtree" />
					</div>
					<h3>{ __( 'Your fleet is empty', 'yacht-booking-system' ) }</h3>
					<p>{ __( 'Add your first yacht to start taking bookings.', 'yacht-booking-system' ) }</p>
					<div className="ybs-empty-state__actions">
						<button className="ybs-btn is-primary" onClick={ () => navigate( 'yachts/new' ) }>
							<span className="dashicons dashicons-plus-alt2" />
							{ __( 'Add New Yacht', 'yacht-booking-system' ) }
						</button>
						{ ! dummySeeded && (
							<button className="ybs-btn" onClick={ importDummyYachts } disabled={ importing }>
								<span className="dashicons dashicons-download" />
								{ importing ? __( 'Importing…', 'yacht-booking-system' ) : __( 'Import Dummy Data', 'yacht-booking-system' ) }
							</button>
						) }
					</div>
				</div>
			) }

			{ filtered && items.length > 0 && filtered.length === 0 && (
				<div className="ybs-empty-state">
					<p>{ __( 'No yachts match your filters.', 'yacht-booking-system' ) }</p>
					<button className="ybs-btn" onClick={ clearFilters }>
						{ __( 'Clear filters', 'yacht-booking-system' ) }
					</button>
				</div>
			) }

			{ filtered && filtered.length > 0 && 'grid' === view && (
				<div className="ybs-yacht-grid">
					{ filtered.map( ( yacht ) => {
						const classes = ( yacht.classes || [] );
						const visibleClasses = classes.slice( 0, 2 );
						const extraCount = classes.length - visibleClasses.length;
						const price = formatPrice( yacht.from_price, currency );

						return (
							<div className="ybs-yacht-card" key={ yacht.id }>
								<div
									className="ybs-yacht-card__media"
									style={ yacht.thumbnail ? { backgroundImage: `url(${ yacht.thumbnail })` } : {} }
									onClick={ () => navigate( `yachts/${ yacht.id }/edit` ) }
								>
									{ ! yacht.thumbnail && (
										<span className="dashicons dashicons-palmtree ybs-yacht-card__placeholder" />
									) }
									<span className="ybs-yacht-card__status">
										<StatusBadge status={ yacht.status } />
									</span>
									{ price && <span className="ybs-yacht-card__price">{ sprintf( __( 'From %s', 'yacht-booking-system' ), price ) }</span> }
								</div>

								<div className="ybs-yacht-card__body">
									<h3 className="ybs-yacht-card__title" onClick={ () => navigate( `yachts/${ yacht.id }/edit` ) }>
										{ yacht.title || __( '(untitled yacht)', 'yacht-booking-system' ) }
									</h3>

									<div className="ybs-yacht-card__location">
										<span className="dashicons dashicons-location" />
										{ yacht.location?.name || __( 'No location set', 'yacht-booking-system' ) }
									</div>

									<div className="ybs-yacht-card__meta">
										<span className="ybs-yacht-card__meta-item">
											<span className="dashicons dashicons-groups" />
											{ sprintf( __( '%d guests', 'yacht-booking-system' ), yacht.capacity || 0 ) }
										</span>
									</div>

									{ classes.length > 0 && (
										<div className="ybs-yacht-card__tags">
											{ visibleClasses.map( ( name ) => (
												<span className="ybs-tag-chip" key={ name }>{ name }</span>
											) ) }
											{ extraCount > 0 && <span className="ybs-tag-chip is-muted">+{ extraCount }</span> }
										</div>
									) }
								</div>

								<div className="ybs-yacht-card__footer">
									<button className="ybs-btn" onClick={ () => navigate( `yachts/${ yacht.id }/edit` ) }>
										<span className="dashicons dashicons-edit" />
										{ __( 'Edit', 'yacht-booking-system' ) }
									</button>
									{ yacht.permalink && 'publish' === yacht.status && (
										<a className="ybs-btn" href={ yacht.permalink } target="_blank" rel="noreferrer">
											<span className="dashicons dashicons-visibility" />
											{ __( 'View', 'yacht-booking-system' ) }
										</a>
									) }
									<button
										className="ybs-btn is-danger ybs-yacht-card__delete"
										onClick={ () => remove( yacht.id, yacht.title ) }
										aria-label={ __( 'Delete', 'yacht-booking-system' ) }
									>
										<span className="dashicons dashicons-trash" />
									</button>
								</div>
							</div>
						);
					} ) }
				</div>
			) }

			{ filtered && filtered.length > 0 && 'list' === view && (
				<div className="ybs-yacht-list">
					{ filtered.map( ( yacht ) => {
						const classes = ( yacht.classes || [] );
						const visibleClasses = classes.slice( 0, 3 );
						const extraCount = classes.length - visibleClasses.length;
						const price = formatPrice( yacht.from_price, currency );

						return (
							<div className="ybs-yacht-row" key={ yacht.id }>
								<div
									className="ybs-yacht-row__thumb"
									style={ yacht.thumbnail ? { backgroundImage: `url(${ yacht.thumbnail })` } : {} }
									onClick={ () => navigate( `yachts/${ yacht.id }/edit` ) }
								>
									{ ! yacht.thumbnail && <span className="dashicons dashicons-palmtree" /> }
								</div>

								<div className="ybs-yacht-row__main" onClick={ () => navigate( `yachts/${ yacht.id }/edit` ) }>
									<div className="ybs-yacht-row__title-line">
										<h3 className="ybs-yacht-row__title">
											{ yacht.title || __( '(untitled yacht)', 'yacht-booking-system' ) }
										</h3>
										<StatusBadge status={ yacht.status } />
									</div>
									<div className="ybs-yacht-row__location">
										<span className="dashicons dashicons-location" />
										{ yacht.location?.name || __( 'No location set', 'yacht-booking-system' ) }
									</div>
								</div>

								<div className="ybs-yacht-row__meta">
									<span className="ybs-yacht-card__meta-item">
										<span className="dashicons dashicons-groups" />
										{ sprintf( __( '%d guests', 'yacht-booking-system' ), yacht.capacity || 0 ) }
									</span>
								</div>

								<div className="ybs-yacht-row__tags">
									{ visibleClasses.map( ( name ) => (
										<span className="ybs-tag-chip" key={ name }>{ name }</span>
									) ) }
									{ extraCount > 0 && <span className="ybs-tag-chip is-muted">+{ extraCount }</span> }
								</div>

								<div className="ybs-yacht-row__price">
									{ price ? sprintf( __( 'From %s', 'yacht-booking-system' ), price ) : '—' }
								</div>

								<div className="ybs-yacht-row__actions">
									<button className="ybs-btn" onClick={ () => navigate( `yachts/${ yacht.id }/edit` ) }>
										<span className="dashicons dashicons-edit" />
										{ __( 'Edit', 'yacht-booking-system' ) }
									</button>
									{ yacht.permalink && 'publish' === yacht.status && (
										<a className="ybs-btn" href={ yacht.permalink } target="_blank" rel="noreferrer" title={ __( 'View', 'yacht-booking-system' ) }>
											<span className="dashicons dashicons-visibility" />
										</a>
									) }
									<button
										className="ybs-btn is-danger"
										onClick={ () => remove( yacht.id, yacht.title ) }
										aria-label={ __( 'Delete', 'yacht-booking-system' ) }
										title={ __( 'Delete', 'yacht-booking-system' ) }
									>
										<span className="dashicons dashicons-trash" />
									</button>
								</div>
							</div>
						);
					} ) }
				</div>
			) }
		</div>
	);
}
