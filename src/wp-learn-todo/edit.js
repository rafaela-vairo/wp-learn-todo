/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/
 */
import { __ } from '@wordpress/i18n';

/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';

/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 */
import './editor.scss';

import { PanelBody, TextControl, RangeControl, Spinner, Button } from '@wordpress/components'; 
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

export default function Edit({ attributes, setAttributes }) {
    const blockProps = useBlockProps();
    const { authorQuery, maxResults } = attributes;

    // Local buffer states for inputs to stop keystroke/slider auto-triggers
    const [ searchKeyword, setSearchKeyword ] = useState( authorQuery );
    const [ localMaxResults, setLocalMaxResults ] = useState( maxResults );

    // State management for live API data
    const [ results, setResults ] = useState( [] );
    const [ isLoading, setIsLoading ] = useState( false );
    const [ hasError, setHasError ] = useState( false );

    // Triggers search updates strictly upon clicking the button
    const handleSearchSubmit = () => {
        if ( ! searchKeyword.trim() ) {
            setResults([]);
            return;
        }
        setAttributes({
            authorQuery: searchKeyword,
            maxResults: localMaxResults
        });
    };

    useEffect( () => {
        // Safeguard: Do not query when block is first dropped into the editor with empty strings
        if ( ! authorQuery || ! authorQuery.trim() ) {
            setResults([]);
            setIsLoading(false);
            return;
        }

        setIsLoading(true);
        setHasError(false);

        const internalPath = `/dspace-block/v1/search?author=${ encodeURIComponent( authorQuery ) }&size=${ maxResults }`;

        apiFetch({ path: internalPath, method: 'GET' })
            .then( ( response ) => {
                const items = response?._embedded?.searchResult?._embedded?.objects || [];
                
                const formattedResults = items.map( ( item ) => {
                    const objectData = item._embedded?.indexableObject;
                    const itemId = objectData?.id || '';
                    
                    // Maps link to human-readable publication summary UI wrapper page
                    const publicUrl = itemId 
                        ? `https://demo.dspace.org/entities/publication/${ itemId }` 
                        : '#';

                    return {
                        id: itemId || Math.random().toString(), 
                        title: objectData?.name || __( 'Untitled Publication', 'dspace-block' ),
                        author: authorQuery, 
                        url: publicUrl
                    };
                } );

                setResults( formattedResults );
                setIsLoading(false);
            } )
            .catch( ( error ) => {
                console.error( 'DSpace Proxy Error:', error );
                setHasError(true);
                setIsLoading(false);
            } );
    }, [ authorQuery ] ); // Only runs when authorQuery officially saves via button click

    return (
        <>
            <InspectorControls>
                <PanelBody title={ __( 'Dspace Settings', 'dspace-block' ) } initialOpen={ true }>
                    <div className="dspace-search-field-wrapper" style={ { marginBottom: '15px' } }>
                        <TextControl
                            label={ __( 'Author Search', 'dspace-block' ) }
                            value={ searchKeyword } 
                            onChange={ ( newValue ) => setSearchKeyword( newValue ) }
                            help={ __( 'Type name and click search.', 'dspace-block' ) }
                        />
                        <RangeControl
                            label={ __( 'Max Results', 'dspace.block' ) }
                            value={ localMaxResults }
                            onChange={ ( newValue ) => setLocalMaxResults( newValue ) }
                            min={ 1 }
                            max={ 10 }
                        />
                        <Button 
                            variant="primary" 
                            onClick={ handleSearchSubmit }
                            isBusy={ isLoading }
                            style={ { width: '100%', justifyContent: 'center', marginTop: '10px' } }
                        >
                            { __( 'Search Repository', 'dspace-block' ) }
                        </Button>
                    </div>
                </PanelBody>
            </InspectorControls>

            <div { ...blockProps }>
                <div className="dspace-query-preview-box">
                    <h4>{ __( 'DSpace Live API Preview', 'dspace-block' ) }</h4>
                    
                    { isLoading && (
                        <div className="dspace-loading">
                            <Spinner /> <p>{ __( 'Fetching repository data...', 'dspace-block' ) }</p>
                        </div>
                    ) }

                    { hasError && ! isLoading && (
                        <p style={ { color: '#cc1818' } }>
                            { __( 'Error connecting to DSpace repository.', 'dspace-block' ) }
                        </p>
                    ) }

                    { ! isLoading && ! hasError && results.length === 0 && (
                        <p>{ __( 'No items found. Enter an author in the sidebar settings and hit Search.', 'dspace-block' ) }</p>
                    ) }

                    { ! isLoading && ! hasError && results.length > 0 && (
                        <ul className="dspace-results-list">
                            { results.map( ( item ) => (
                                <li key={ item.id } className="dspace-item">
                                    <a href={ item.url } target="_blank" rel="noreferrer">
                                        { item.title }
                                    </a>
                                </li>
                            ) ) }
                        </ul>
                    ) }
                </div>
            </div>
        </>
    );
}