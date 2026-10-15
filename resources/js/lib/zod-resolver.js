/**
 * Minimal react-hook-form resolver for a zod schema, in place of
 * @hookform/resolvers (its peer range conflicts with the installed zod).
 * Keeps the first issue per field path, which is all the forms render.
 *
 *   useForm({ resolver: zodResolver(schema) })
 */
export function zodResolver(schema) {
    return async (values) => {
        const result = await schema.safeParseAsync(values);
        if (result.success) return { values: result.data, errors: {} };

        const errors = {};
        for (const issue of result.error.issues) {
            if (issue.path.length === 0) continue;

            let node = errors;
            issue.path.forEach((key, index) => {
                if (index === issue.path.length - 1) {
                    node[key] ??= { type: issue.code, message: issue.message };
                } else {
                    node = node[key] ??= {};
                }
            });
        }
        return { values: {}, errors };
    };
}
