import { useCallback, useId, useRef, useState } from '@wordpress/element';
import { createPortal } from 'react-dom';

const TOOLTIP_GAP_PX = 8;

function getPortalTarget() {
	if ( typeof document === 'undefined' ) {
		return null;
	}
	return document.body;
}

/**
 * Tooltip for icon-rail navigation (collapsed sidebar). Portaled to document.body
 * with styles from app-shell.css (not Tailwind — utilities are scoped to #pr-root).
 */
export function IconRailTooltip( { label, children } ) {
	const tooltipId = useId();
	const anchorRef = useRef( null );
	const [ visible, setVisible ] = useState( false );
	const [ position, setPosition ] = useState( { top: 0, left: 0 } );

	const updatePosition = useCallback( () => {
		const el = anchorRef.current;
		if ( ! el ) {
			return;
		}
		const rect = el.getBoundingClientRect();
		setPosition( {
			top: rect.top + rect.height / 2,
			left: rect.right + TOOLTIP_GAP_PX,
		} );
	}, [] );

	const show = useCallback( () => {
		updatePosition();
		setVisible( true );
	}, [ updatePosition ] );

	const hide = useCallback( () => {
		setVisible( false );
	}, [] );

	const portalTarget = getPortalTarget();

	return (
		<>
			<span
				ref={ anchorRef }
				className="pr-icon-rail-anchor"
				onMouseEnter={ show }
				onMouseLeave={ hide }
				onFocus={ show }
				onBlur={ hide }
			>
				{ children }
			</span>
			{ visible && portalTarget
				? createPortal(
						<span
							id={ tooltipId }
							role="tooltip"
							className="pr-icon-rail-tooltip"
							style={ {
								top: `${ position.top }px`,
								left: `${ position.left }px`,
							} }
						>
							{ label }
						</span>,
						portalTarget
				  )
				: null }
		</>
	);
}
