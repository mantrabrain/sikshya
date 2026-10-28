# Selling courses through WooCommerce

For a shop that already runs WooCommerce, this is less a feature than the
removal of a reason not to use Sikshya at all: the gateways, taxes, invoices,
accounting exports and customer accounts already work, and nobody wants a
second checkout standing beside them.

It is **free**, for the same reason the importers are. Sikshya's own checkout is
free too, so offering WooCommerce as the front end instead is consistency, not
generosity.

- Nothing to enable. The integration hooks WooCommerce's own actions, so on a
  site without it nothing here ever runs.
- **Products → Edit product → Courses**: pick the courses the product sells.

## How it works

| When | Sikshya does |
| --- | --- |
| Order reaches **completed** or **processing**, or the gateway reports payment complete | Enrols the buyer in every course on the order |
| Order is **refunded**, **cancelled** or **failed** | Closes the enrolments *that order* created |
| A line item is **fully refunded** | Closes only that line's courses |
| The buyer already has the course | Refuses to add it to the cart, with a notice |

Three decisions are worth knowing about.

**`processing` grants access, not only `completed`.** A shop that sells nothing
but courses often leaves digital orders in `processing` for ever, because there
is nothing to ship. Waiting for `completed` would mean half those customers
never got in. All three triggers are idempotent, so an order that passes
through two of them enrols once — which matters because WooCommerce fires on
every transition and gateways retry their callbacks.

**A refund closes only what that order opened.** Every enrolment created this
way is stamped with its order (`wc:1234`). A refund looks for exactly that
stamp, so a learner who was also enrolled by an administrator, or who bought
the same course in a different order, keeps that access. Revoking access given
for an unrelated reason is a mistake a customer notices from the outside.

**Nothing is deleted.** A revoked enrolment becomes `cancelled`: the record
that somebody once had access, and whatever progress they made, survives. Buy
again later and the same enrolment is reopened rather than replaced, so that
progress is still theirs.

## Guest checkout

WooCommerce will happily take money from somebody with no account. Course
access belongs to a person, so an account is created from the billing address
and WordPress emails them a link to set their own password. If the billing
email already belongs to an account, the course goes to that account — which is
what the buyer expects.

A site that would rather refuse can turn this off:

```php
add_filter( 'sikshya_woocommerce_create_account_for_guest', '__return_false' );
```

Do that and you should also require an account at checkout, or the money
arrives and the course does not. The role new accounts get is
`sikshya_woocommerce_guest_role` (default `sikshya_student`).

## On the course page

A course sold through WooCommerce shows the WooCommerce price and an add-to-cart
button instead of Sikshya's own cart — otherwise the page offers two ways to
pay, whichever the buyer picks makes half the shop's reporting wrong, and the
two carts disagree about what was bought.

The takeover is two hooks, so any other integration can do the same:

```php
add_filter( 'sikshya_course_purchase_handled', fn( $handled, $course_id ) => true, 10, 2 );
add_action( 'sikshya_course_purchase_actions', fn( $course_id ) => print( '…your buttons…' ) );
```

## Hooks

| Hook | Use |
| --- | --- |
| `sikshya_woocommerce_access_granted` | After an order granted access (`$user_id`, `$course_ids`, `$order`). |
| `sikshya_woocommerce_access_revoked` | After a refund or cancellation closed access. |
| `sikshya_woocommerce_create_account_for_guest` | Refuse account creation for guest orders. |
| `sikshya_woocommerce_guest_role` | The role a created account gets. |

## Limits, stated plainly

- **Subscriptions are not handled.** WooCommerce Subscriptions renewals and
  expiries do not grant or revoke anything here; a subscription product sells
  the course once, at the first payment. Sikshya Pro has its own subscriptions
  add-on, and the two are not wired together.
- **Course *price* still lives in Sikshya** and is what Sikshya's own checkout
  would charge. The product's price is what WooCommerce charges. They are not
  kept in step, deliberately — a shop prices in its shop — but if you sell the
  same course both ways you now have two prices to remember.
- **Memberships, bundles-of-products and "grant on first payment of an
  instalment plan"** are not special-cased.
- **No reporting bridge.** Sikshya's own sales reports do not include
  WooCommerce orders; WooCommerce's reports do. Each system reports what it
  took the money for.
- A variation may carry its own courses, and falls back to the parent
  product's when it does not.
