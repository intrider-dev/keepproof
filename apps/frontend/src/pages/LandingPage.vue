<script setup lang="ts">
import { useRoute } from 'vue-router'
import AppIcon from '../components/AppIcon.vue'
import BrandMark from '../components/BrandMark.vue'

const route = useRoute()
</script>

<template>
  <div class="marketing-page">
    <header class="marketing-nav">
      <RouterLink class="brand" to="/" aria-label="KeepProof home">
        <BrandMark />
        <span>KeepProof</span>
      </RouterLink>
      <nav aria-label="Landing page">
        <a href="#how-it-works">How it works</a>
        <a href="#privacy">Privacy</a>
      </nav>
      <div class="marketing-nav__actions">
        <RouterLink class="text-link" to="/login">Sign in</RouterLink>
        <RouterLink class="button button--secondary button--small desktop-only" to="/register">
          Create account
        </RouterLink>
      </div>
    </header>

    <main id="main-content" tabindex="-1">
      <div v-if="route.query.deleted === '1'" class="marketing-notice" role="status">
        Your local demo data was cleared. Nothing was sent anywhere.
      </div>

      <section class="hero-section">
        <div class="hero-copy">
          <p class="eyebrow"><span></span> Private receipt tracking</p>
          <h1>Never miss a return window or warranty deadline.</h1>
          <p class="hero-copy__lead">
            Keep your proof of purchase in one private place. KeepProof turns receipt details into
            clear, explainable deadlines—only after you review them.
          </p>
          <div class="hero-actions">
            <RouterLink class="button button--primary button--large" to="/demo">
              Try the local demo
              <AppIcon name="arrow" />
            </RouterLink>
            <RouterLink class="button button--quiet button--large" to="/register">
              Create a private account
            </RouterLink>
          </div>
          <p class="hero-proof">
            <span><AppIcon name="shield" :size="17" /> Synthetic demo data</span>
            <span><AppIcon name="spark" :size="17" /> No external AI required</span>
          </p>
        </div>

        <div class="hero-product" aria-label="Preview of a receipt and its return deadline">
          <div class="receipt-preview">
            <div class="receipt-preview__top">
              <BrandMark />
              <span>BRIGHTLINE HOME</span>
            </div>
            <div class="receipt-preview__rule"></div>
            <div class="receipt-preview__meta">
              <span>JUL 18, 2026</span>
              <span>BH-73104</span>
            </div>
            <div class="receipt-preview__item">
              <span>Linen floor lamp</span>
              <strong>$139.50</strong>
            </div>
            <div class="receipt-preview__lines"><i></i><i></i><i></i></div>
            <div class="receipt-preview__total">
              <span>TOTAL</span>
              <strong>$139.50</strong>
            </div>
            <div class="receipt-preview__footer">30 DAY RETURNS · 12 MONTH WARRANTY</div>
          </div>

          <div class="floating-card floating-card--review">
            <span class="floating-card__icon"><AppIcon name="check" :size="18" /></span>
            <span>
              <small>Human reviewed</small>
              <strong>Details confirmed</strong>
            </span>
          </div>
          <div class="floating-card floating-card--deadline">
            <span class="date-tile"><b>17</b><small>AUG</small></span>
            <span>
              <small>Return by</small>
              <strong>28 days left</strong>
            </span>
          </div>
          <div class="privacy-orbit"><AppIcon name="shield" :size="19" /> Processed locally</div>
        </div>
      </section>

      <section id="how-it-works" class="landing-section">
        <div class="section-intro">
          <p class="eyebrow">A calmer paper trail</p>
          <h2>From receipt to reminder, with you in control.</h2>
          <p>
            KeepProof does the tedious organizing while keeping the important decisions visible.
          </p>
        </div>
        <ol class="steps-grid">
          <li>
            <span class="step-number">01</span>
            <span class="feature-icon"><AppIcon name="upload" /></span>
            <h3>Add your proof</h3>
            <p>Upload a JPG, PNG, or PDF, or enter the purchase yourself.</p>
          </li>
          <li>
            <span class="step-number">02</span>
            <span class="feature-icon"><AppIcon name="check" /></span>
            <h3>Review every detail</h3>
            <p>See field-level confidence, correct mistakes, then confirm what becomes active.</p>
          </li>
          <li>
            <span class="step-number">03</span>
            <span class="feature-icon"><AppIcon name="bell" /></span>
            <h3>Act before the date</h3>
            <p>Get reminders, add an ICS event, or prepare your proof package.</p>
          </li>
        </ol>
      </section>

      <section id="privacy" class="privacy-section">
        <div class="privacy-section__visual">
          <div class="privacy-lockup">
            <span><AppIcon name="shield" :size="32" /></span>
            <div>
              <small>Default processing path</small>
              <strong>Your device → your private storage</strong>
            </div>
          </div>
          <ul>
            <li>
              <AppIcon name="check" :size="18" /> Local OCR and manual entry work without a paid API
            </li>
            <li>
              <AppIcon name="check" :size="18" /> External AI stays off until you explicitly allow
              it
            </li>
            <li>
              <AppIcon name="check" :size="18" /> Export your data or remove it from one place
            </li>
          </ul>
        </div>
        <div class="privacy-section__copy">
          <p class="eyebrow">Privacy is product behavior</p>
          <h2>Your receipt is not an ad signal.</h2>
          <p>
            KeepProof has no advertising trackers. Receipt extraction runs locally by default, and
            machine-read values never become deadlines until you confirm them.
          </p>
          <p class="fine-print">
            KeepProof tracks the terms you enter or confirm. It does not provide legal advice or
            determine rights under local law.
          </p>
          <RouterLink class="inline-link" to="/demo">
            See the privacy controls in the demo <AppIcon name="arrow" :size="17" />
          </RouterLink>
        </div>
      </section>

      <section class="final-cta">
        <BrandMark />
        <p class="eyebrow">A receipt you can find. A date you can trust.</p>
        <h2>Give your next purchase a clear deadline.</h2>
        <div class="hero-actions">
          <RouterLink class="button button--primary button--large" to="/demo"
            >Try the local demo</RouterLink
          >
          <RouterLink class="button button--quiet button--large" to="/register"
            >Create an account</RouterLink
          >
        </div>
      </section>
    </main>

    <footer class="marketing-footer">
      <RouterLink class="brand" to="/"><BrandMark /><span>KeepProof</span></RouterLink>
      <p>Private receipt, return-window, and warranty tracking.</p>
      <span
        ><RouterLink to="/docs/api">API docs</RouterLink> · Open-source · No advertising
        trackers</span
      >
    </footer>
  </div>
</template>
