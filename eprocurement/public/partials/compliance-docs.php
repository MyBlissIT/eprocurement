<?php
/**
 * Public SCM documents page.
 *
 * Displays a list of downloadable SCM documents (e.g., BBBEE forms,
 * tax clearance templates) managed by the organisation.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$slug      = get_option( 'eprocurement_frontend_page_slug', 'tenders' );
$nav_items = Eprocurement_Public::get_nav_items();

$compliance_model = new Eprocurement_Compliance_Docs();
$section_title    = Eprocurement_Compliance_Docs::get_section_title();
$docs             = $compliance_model->get_all();
?>
<div class="eproc-wrap">

    <!-- Navigation Bar -->
    <nav class="eproc-navbar">
        <div class="eproc-navbar-inner">
            <a href="<?php echo esc_url( home_url( "/{$slug}/" ) ); ?>" class="eproc-navbar-brand">
                <?php echo esc_html__( 'eProcurement Portal', 'eprocurement' ); ?>
            </a>
            <div class="eproc-navbar-links">
                <?php foreach ( $nav_items as $nav_item ) : ?>
                    <a href="<?php echo esc_url( $nav_item['url'] ); ?>" class="eproc-nav-link">
                        <?php echo esc_html( $nav_item['label'] ); ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="eproc-navbar-actions">
                <?php if ( is_user_logged_in() && Eprocurement_Roles::is_bidder() ) : ?>
                    <a href="<?php echo esc_url( home_url( "/{$slug}/my-account/" ) ); ?>" class="eproc-btn eproc-btn-outline">
                        <?php echo esc_html__( 'My Dashboard', 'eprocurement' ); ?>
                    </a>
                <?php elseif ( ! is_user_logged_in() ) : ?>
                    <a href="<?php echo esc_url( home_url( "/{$slug}/login/" ) ); ?>" class="eproc-btn eproc-btn-outline">
                        <?php echo esc_html__( 'Login', 'eprocurement' ); ?>
                    </a>
                    <a href="<?php echo esc_url( home_url( "/{$slug}/register/" ) ); ?>" class="eproc-btn eproc-btn-primary">
                        <?php echo esc_html__( 'Register', 'eprocurement' ); ?>
                    </a>
                <?php endif; ?>
            </div>
            <button class="eproc-navbar-toggle" aria-label="<?php echo esc_attr__( 'Toggle navigation', 'eprocurement' ); ?>">
                <span class="eproc-navbar-toggle-icon"></span>
            </button>
        </div>
    </nav>

    <!-- Page Header -->
    <section class="eproc-page-header">
        <h1 class="eproc-page-title"><?php echo esc_html( $section_title ); ?></h1>
        <p class="eproc-page-description">
            <?php echo esc_html__( 'Download the standard SCM documents required for bid submissions. These documents apply to all current and future tenders.', 'eprocurement' ); ?>
        </p>
    </section>

    <!-- Document List -->
    <section class="eproc-compliance-list">
        <?php if ( empty( $docs ) ) : ?>
            <div class="eproc-empty-state">
                <p><?php echo esc_html__( 'No SCM documents have been uploaded yet.', 'eprocurement' ); ?></p>
            </div>
        <?php else : ?>
            <div class="eproc-doc-list">
                <?php foreach ( $docs as $doc ) : ?>
                    <div class="eproc-doc-item">
                        <div class="eproc-doc-item-icon">
                            <span class="eproc-file-icon" data-type="<?php echo esc_attr( $doc->file_type ); ?>"></span>
                        </div>
                        <div class="eproc-doc-item-info">
                            <h3 class="eproc-doc-label">
                                <?php echo esc_html( $doc->label ?: $doc->file_name ); ?>
                            </h3>
                            <?php if ( $doc->description ) : ?>
                                <p class="eproc-doc-description"><?php echo esc_html( $doc->description ); ?></p>
                            <?php endif; ?>
                            <span class="eproc-doc-size">
                                <?php echo esc_html( Eprocurement_Public::format_file_size( (int) $doc->file_size ) ); ?>
                            </span>
                        </div>
                        <div class="eproc-doc-item-action">
                            <a
                                href="<?php echo esc_url( Eprocurement_Downloads::get_download_link( (int) $doc->id, 'compliance' ) ); ?>"
                                class="eproc-btn eproc-btn-primary eproc-btn-sm"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <?php echo esc_html__( 'Download', 'eprocurement' ); ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

</div><!-- .eproc-wrap -->
