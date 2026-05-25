import { useEffect, useState } from '@wordpress/element';

export function useDebouncedValue( value, delayMs = 300 ) {
	const [ debounced, setDebounced ] = useState( value );

	useEffect( () => {
		const timer = setTimeout( () => setDebounced( value ), delayMs );

		return () => clearTimeout( timer );
	}, [ value, delayMs ] );

	return debounced;
}
