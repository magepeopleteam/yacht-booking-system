import { __ } from '@wordpress/i18n';
import { useEffect, useRef, useState } from '@wordpress/element';

const HOURS = Array.from( { length: 12 }, ( _, i ) => i + 1 );
const MINUTES = Array.from( { length: 12 }, ( _, i ) => i * 5 );
const PRESETS = [
	{ value: '09:00', label: __( '9:00 AM', 'magepeople-yacht-booking-system' ) },
	{ value: '12:00', label: __( '12:00 PM', 'magepeople-yacht-booking-system' ) },
	{ value: '15:00', label: __( '3:00 PM', 'magepeople-yacht-booking-system' ) },
	{ value: '18:00', label: __( '6:00 PM', 'magepeople-yacht-booking-system' ) },
	{ value: '20:00', label: __( '8:00 PM', 'magepeople-yacht-booking-system' ) },
];

function pad( n ) {
	return String( n ).padStart( 2, '0' );
}

function parseValue( value ) {
	const m = /^(\d{1,2}):(\d{2})/.exec( value || '' );
	if ( ! m ) {
		return null;
	}
	return { hour: parseInt( m[ 1 ], 10 ), minute: parseInt( m[ 2 ], 10 ) };
}

function to12( hour, minute ) {
	const period = hour >= 12 ? 'PM' : 'AM';
	let hour12 = hour % 12;
	if ( hour12 === 0 ) {
		hour12 = 12;
	}
	return { hour12, minute, period };
}

function from12( hour12, minute, period ) {
	let hour = hour12 % 12;
	if ( period === 'PM' ) {
		hour += 12;
	}
	return { hour, minute };
}

/**
 * A trigger input styled and behaving like mage-eventpress's admin time
 * field - a native `<input type="time">` (so the browser still renders a
 * localized "10:00 AM"-style value) whose native picker is suppressed in
 * favor of this custom hour/minute/AM-PM popover, ported to this plugin's
 * own `ybs-` class names and orange accent.
 */
export default function TimeField( { value, onChange, placeholder } ) {
	const [ open, setOpen ] = useState( false );
	const [ hour, setHour ] = useState( 9 );
	const [ minute, setMinute ] = useState( 0 );
	const wrapRef = useRef( null );

	useEffect( () => {
		if ( ! open ) {
			return;
		}

		const onOutside = ( e ) => {
			if ( wrapRef.current && ! wrapRef.current.contains( e.target ) ) {
				setOpen( false );
			}
		};
		const onKey = ( e ) => {
			if ( e.key === 'Escape' ) {
				setOpen( false );
			}
		};

		document.addEventListener( 'mousedown', onOutside );
		document.addEventListener( 'keydown', onKey );
		return () => {
			document.removeEventListener( 'mousedown', onOutside );
			document.removeEventListener( 'keydown', onKey );
		};
	}, [ open ] );

	const openPicker = () => {
		const parsed = parseValue( value );
		const now = new Date();
		setHour( parsed ? parsed.hour : now.getHours() );
		setMinute( parsed ? parsed.minute : Math.round( now.getMinutes() / 5 ) * 5 % 60 );
		setOpen( true );
	};

	const apply = ( nextHour, nextMinute, closeAfter ) => {
		setHour( nextHour );
		setMinute( nextMinute );
		onChange( pad( nextHour ) + ':' + pad( nextMinute ) );
		if ( closeAfter ) {
			setOpen( false );
		}
	};

	const parts = to12( hour, minute );
	const hasCustomMinute = parts.minute % 5 !== 0;

	return (
		<span className="ybs-time-input-wrap" ref={ wrapRef }>
			<input
				type="time"
				className="ybs-time-input ybs-custom-time-enabled"
				readOnly
				autoComplete="off"
				inputMode="none"
				placeholder={ placeholder }
				value={ value || '' }
				onMouseDown={ ( e ) => {
					e.preventDefault();
					if ( ! open ) {
						openPicker();
					}
				} }
				onKeyDown={ ( e ) => {
					if ( e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown' ) {
						e.preventDefault();
						openPicker();
					} else if ( e.key === 'Escape' ) {
						setOpen( false );
					} else if ( e.key.length === 1 || e.key === 'Backspace' || e.key === 'Delete' ) {
						e.preventDefault();
					}
				} }
				onChange={ () => {} }
			/>

			{ open && (
				<div className="ybs-custom-time-picker is-open" role="dialog" aria-modal="false" aria-label={ __( 'Choose time', 'magepeople-yacht-booking-system' ) }>
					<div className="ybs-custom-time-picker__preview" aria-live="polite">
						<span className="ybs-custom-time-picker__preview-hour">{ pad( parts.hour12 ) }</span>
						<span className="ybs-custom-time-picker__preview-colon">:</span>
						<span className="ybs-custom-time-picker__preview-minute">{ pad( parts.minute ) }</span>
						<span className="ybs-custom-time-picker__preview-period">{ parts.period }</span>
					</div>

					<div className="ybs-custom-time-picker__period" role="group" aria-label={ __( 'AM or PM', 'magepeople-yacht-booking-system' ) }>
						{ [ 'AM', 'PM' ].map( ( p ) => (
							<button
								key={ p }
								type="button"
								className={ 'ybs-custom-time-picker__period-btn' + ( parts.period === p ? ' is-selected' : '' ) }
								onClick={ () => {
									const next = from12( parts.hour12, parts.minute, p );
									apply( next.hour, next.minute, false );
								} }
							>
								{ p }
							</button>
						) ) }
					</div>

					<div className="ybs-custom-time-picker__grid">
						<div className="ybs-custom-time-picker__section">
							<div className="ybs-custom-time-picker__label">{ __( 'Hour', 'magepeople-yacht-booking-system' ) }</div>
							<div className="ybs-custom-time-picker__hours">
								{ HOURS.map( ( h ) => (
									<button
										key={ h }
										type="button"
										className={ 'ybs-custom-time-picker__chip' + ( parts.hour12 === h ? ' is-selected' : '' ) }
										onClick={ () => {
											const next = from12( h, parts.minute, parts.period );
											apply( next.hour, next.minute, false );
										} }
									>
										{ pad( h ) }
									</button>
								) ) }
							</div>
						</div>
						<div className="ybs-custom-time-picker__section">
							<div className="ybs-custom-time-picker__label">{ __( 'Minute', 'magepeople-yacht-booking-system' ) }</div>
							<div className="ybs-custom-time-picker__minutes">
								{ MINUTES.map( ( m ) => (
									<button
										key={ m }
										type="button"
										className={ 'ybs-custom-time-picker__chip' + ( parts.minute === m ? ' is-selected' : '' ) }
										onClick={ () => apply( hour, m, false ) }
									>
										{ pad( m ) }
									</button>
								) ) }
								{ hasCustomMinute && (
									<button type="button" className="ybs-custom-time-picker__chip is-custom is-selected">
										{ pad( parts.minute ) }
									</button>
								) }
							</div>
						</div>
					</div>

					<div className="ybs-custom-time-picker__presets" role="group" aria-label={ __( 'Quick times', 'magepeople-yacht-booking-system' ) }>
						{ PRESETS.map( ( preset ) => (
							<button
								key={ preset.value }
								type="button"
								className="ybs-custom-time-picker__preset"
								onClick={ () => {
									const parsed = parseValue( preset.value );
									apply( parsed.hour, parsed.minute, true );
								} }
							>
								{ preset.label }
							</button>
						) ) }
					</div>

					<div className="ybs-custom-time-picker__foot">
						<button
							type="button"
							className="ybs-custom-time-picker__now"
							onClick={ () => {
								const now = new Date();
								apply( now.getHours(), Math.round( now.getMinutes() / 5 ) * 5 % 60, true );
							} }
						>
							{ __( 'Now', 'magepeople-yacht-booking-system' ) }
						</button>
						<div className="ybs-custom-time-picker__foot-actions">
							<button
								type="button"
								className="ybs-custom-time-picker__clear"
								onClick={ () => {
									onChange( '' );
									setOpen( false );
								} }
							>
								{ __( 'Clear', 'magepeople-yacht-booking-system' ) }
							</button>
							<button
								type="button"
								className="ybs-custom-time-picker__done"
								onClick={ () => setOpen( false ) }
							>
								{ __( 'Done', 'magepeople-yacht-booking-system' ) }
							</button>
						</div>
					</div>
				</div>
			) }
		</span>
	);
}
